<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\Actions;

use RoundlyConsulting\Comments\DataTransferObjects\UpdateCommentData;
use RoundlyConsulting\Comments\Enums\CommentStatus;
use RoundlyConsulting\Comments\Events\CommentUpdated;
use RoundlyConsulting\Comments\Exceptions\CommentsLockedException;
use RoundlyConsulting\Comments\Exceptions\InvalidCommentBodyException;
use RoundlyConsulting\Comments\Models\Comment;
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

        $data->comment->update([
            'comment' => $body,
            'status' => $this->statusAfterEdit($data->comment->status, $this->blocklist->heldStatus($body)),
        ]);

        $this->syncMentions->execute($data->comment);

        CommentUpdated::dispatch($data->comment);

        return $data->comment;
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
