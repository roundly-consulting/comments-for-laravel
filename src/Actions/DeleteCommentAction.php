<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\Actions;

use RoundlyConsulting\Comments\Events\CommentDeleted;
use RoundlyConsulting\Comments\Models\Comment;

final class DeleteCommentAction
{
    public function execute(Comment $comment): void
    {
        $comment->delete();

        CommentDeleted::dispatch($comment);
    }
}
