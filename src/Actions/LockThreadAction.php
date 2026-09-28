<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\Actions;

use RoundlyConsulting\Comments\Events\CommentThreadLocked;
use RoundlyConsulting\Comments\Models\Comment;
use RoundlyConsulting\Comments\Support\CommentAuthorizer;

/**
 * Lock the thread under a comment: no new replies anywhere below it, and no edits to it or any
 * comment in it. Gated by the Comment policy's `lock` ability (called with the comment) when
 * `comments.authorization` is on. Idempotent — locking a locked thread keeps its original
 * `locked_at` and fires nothing.
 */
final readonly class LockThreadAction
{
    public function __construct(
        private CommentAuthorizer $authorizer,
    ) {}

    public function execute(Comment $comment): Comment
    {
        $this->authorizer->authorize('lock', [$comment]);

        if ($comment->isLocked()) {
            return $comment;
        }

        $comment->update(['locked_at' => now()]);

        CommentThreadLocked::dispatch($comment);

        return $comment;
    }
}
