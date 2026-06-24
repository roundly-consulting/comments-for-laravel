<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\Actions;

use RoundlyConsulting\Comments\Events\CommentDeleted;
use RoundlyConsulting\Comments\Models\Comment;
use RoundlyConsulting\Comments\Support\CommentAuthorizer;

final class DeleteCommentAction
{
    public function __construct(
        private readonly CommentAuthorizer $authorizer,
    ) {}

    public function execute(Comment $comment): void
    {
        $this->authorizer->authorize('delete', [$comment]);

        $comment->delete();

        CommentDeleted::dispatch($comment);
    }
}
