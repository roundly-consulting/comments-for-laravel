<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\Actions;

use RoundlyConsulting\Comments\DataTransferObjects\UpdateCommentData;
use RoundlyConsulting\Comments\Events\CommentUpdated;
use RoundlyConsulting\Comments\Exceptions\CommentRejectedException;
use RoundlyConsulting\Comments\Exceptions\CommentsLockedException;
use RoundlyConsulting\Comments\Exceptions\InvalidCommentBodyException;
use RoundlyConsulting\Comments\Models\Comment;
use RoundlyConsulting\Comments\Support\Blocklist;
use RoundlyConsulting\Comments\Support\CommentAuthorizer;
use RoundlyConsulting\Comments\Support\CommentLocks;

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

        $maxLength = (int) config('comments.max_length', 5000);

        if (mb_strlen($body) > $maxLength) {
            throw InvalidCommentBodyException::tooLong($maxLength);
        }

        if ($this->blocklist->matches($body) && (string) config('comments.blocklist_action', 'reject') === 'reject') {
            throw CommentRejectedException::blocked();
        }

        $data->comment->update(['comment' => $body]);

        $this->syncMentions->execute($data->comment);

        CommentUpdated::dispatch($data->comment);

        return $data->comment;
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
