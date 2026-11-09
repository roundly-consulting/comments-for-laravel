<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\Actions;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Comments\DataTransferObjects\UpdateCommentData;
use RoundlyConsulting\Comments\Enums\CommentStatus;
use RoundlyConsulting\Comments\Events\CommentUpdated;
use RoundlyConsulting\Comments\Exceptions\CommentsLockedException;
use RoundlyConsulting\Comments\Exceptions\InvalidCommentBodyException;
use RoundlyConsulting\Comments\Models\Comment;
use RoundlyConsulting\Comments\Models\CommentRevision;
use RoundlyConsulting\Comments\Support\Blocklist;
use RoundlyConsulting\Comments\Support\CommentAuthorizer;
use RoundlyConsulting\Comments\Support\CommentLocks;
use RoundlyConsulting\Comments\Support\CommentsConfig;

final readonly class UpdateCommentAction
{
    public function __construct(
        private Blocklist $blocklist,
        private CommentAuthorizer $authorizer,
        private CommentLocks $locks,
        private SyncCommentMentionsAction $syncMentions,
    ) {}

    public function execute(UpdateCommentData $data): Comment
    {
        $this->authorizer->authorize('update', [$data->comment]);

        $this->guardLocked($data->comment);

        $body = trim($data->body);

        if ($body === '') {
            throw InvalidCommentBodyException::empty();
        }

        $maxLength = CommentsConfig::maxLength();

        if (mb_strlen($body) > $maxLength) {
            throw InvalidCommentBodyException::tooLong($maxLength);
        }

        $held = $this->blocklist->heldStatus($body);

        if (CommentsConfig::editHistory()) {
            $this->updateWithHistory($data->comment, $body, $held, $data->editor);
        } else {
            $data->comment->update([
                'comment' => $body,
                'status' => $this->statusAfterEdit($data->comment->status, $held),
            ]);
        }

        $this->syncMentions->execute($data->comment);

        CommentUpdated::dispatch($data->comment);

        return $data->comment;
    }

    /**
     * Keep the replaced body as the next revision, then write the edit — in one transaction,
     * under a row lock on the comment. The lock serializes concurrent edits: each one reads the
     * body as the previous edit committed it (never the stale copy its model was loaded with),
     * takes the next revision number, and so every edit lands in the history, in order. An edit
     * that leaves the body as it is records no revision.
     */
    private function updateWithHistory(Comment $comment, string $body, ?CommentStatus $held, ?Model $editor): void
    {
        $comment->getConnection()->transaction(function () use ($comment, $body, $held, $editor): void {
            $current = $comment->newQueryWithoutScopes()
                ->whereKey($comment->getKey())
                ->lockForUpdate()
                ->first();

            if ($current !== null) {
                // Start from the committed row, so the save below writes the edit even when
                // this model was loaded before a concurrent edit changed it.
                $comment->setRawAttributes(array_replace($comment->getAttributes(), [
                    'comment' => $current->getRawOriginal('comment'),
                    'status' => $current->getRawOriginal('status'),
                ]));
                $comment->syncOriginalAttributes(['comment', 'status']);

                if ($current->comment !== $body) {
                    $comment->revisions()->create([
                        // Unordered: Postgres refuses an aggregate next to the relation's ORDER BY.
                        'revision' => (int) CommentRevision::query()->where('comment_id', $comment->getKey())->max('revision') + 1,
                        'body' => $current->comment,
                        'editor_type' => $editor?->getMorphClass(),
                        'editor_id' => $editor?->getKey(),
                    ]);
                }
            }

            $comment->update([
                'comment' => $body,
                'status' => $this->statusAfterEdit($comment->status, $held),
            ]);
        });
    }

    /**
     * An edit runs the blocklist exactly like a write: a blocklisted body is rejected or held at
     * `pending`/`hidden`, so a comment posted clean cannot be edited into spam and stay public.
     * The edit only ever tightens the status — it never lifts a hidden comment to pending, and a
     * clean edit leaves a held comment for a moderator to approve.
     */
    private function statusAfterEdit(CommentStatus $current, ?CommentStatus $held): CommentStatus
    {
        if ($held === CommentStatus::Hidden || ($held === CommentStatus::Pending && $current === CommentStatus::Approved)) {
            return $held;
        }

        return $current;
    }

    /**
     * A comment in a locked thread (its own lock or an ancestor's) or on a locked subject is
     * frozen against edits.
     */
    private function guardLocked(Comment $comment): void
    {
        if ($this->locks->threadLocked($comment)
            || $this->locks->subjectLocked($comment->commentable_type, $comment->commentable_id)) {
            throw CommentsLockedException::make();
        }
    }
}
