<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\Actions;

use RoundlyConsulting\Comments\Models\Comment;
use RoundlyConsulting\Comments\Support\CommentAuthorizer;

final class RestoreCommentAction
{
    public function __construct(
        private readonly CommentAuthorizer $authorizer,
    ) {}

    public function execute(Comment $comment): Comment
    {
        $this->authorizer->authorize('restore', [$comment]);

        $comment->restore();

        return $comment;
    }
}
