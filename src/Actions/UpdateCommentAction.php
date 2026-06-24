<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\Actions;

use RoundlyConsulting\Comments\DataTransferObjects\UpdateCommentData;
use RoundlyConsulting\Comments\Events\CommentUpdated;
use RoundlyConsulting\Comments\Exceptions\CommentRejectedException;
use RoundlyConsulting\Comments\Exceptions\CommentsLockedException;
use RoundlyConsulting\Comments\Exceptions\InvalidCommentBodyException;
use RoundlyConsulting\Comments\Models\Comment;
use RoundlyConsulting\Comments\Models\CommentLock;
use RoundlyConsulting\Comments\Support\Blocklist;
use RoundlyConsulting\Comments\Support\CommentAuthorizer;

final class UpdateCommentAction
{
    public function __construct(
        private readonly Blocklist $blocklist,
        private readonly CommentAuthorizer $authorizer,
        private readonly SyncCommentMentionsAction $syncMentions,
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

    private function guardLocked(Comment $comment): void
    {
        if ($comment->isLocked()) {
            throw CommentsLockedException::make();
        }

        $locked = CommentLock::query()
            ->where('lockable_type', $comment->commentable_type)
            ->where('lockable_id', $comment->commentable_id)
            ->exists();

        if ($locked) {
            throw CommentsLockedException::make();
        }
    }
}
