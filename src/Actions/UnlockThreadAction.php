<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\Actions;

use RoundlyConsulting\Comments\Events\CommentThreadUnlocked;
use RoundlyConsulting\Comments\Models\Comment;
use RoundlyConsulting\Comments\Support\CommentAuthorizer;

/**
 * Lift a comment's thread lock. Gated by the Comment policy's `unlock` ability (called with the
 * comment) when `comments.authorization` is on. Idempotent — unlocking an open thread fires
 * nothing. A lock on an ancestor still covers the comment afterwards.
 */
final readonly class UnlockThreadAction
{
    public function __construct(
        private CommentAuthorizer $authorizer,
    ) {}

    public function execute(Comment $comment): Comment
    {
        $this->authorizer->authorize('unlock', [$comment]);

        if (! $comment->isLocked()) {
            return $comment;
        }

        $comment->update(['locked_at' => null]);

        CommentThreadUnlocked::dispatch($comment);

        return $comment;
    }
}
