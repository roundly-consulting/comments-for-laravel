<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\Actions;

use RoundlyConsulting\Comments\Models\Comment;

final class RestoreCommentAction
{
    public function execute(Comment $comment): Comment
    {
        $comment->restore();

        return $comment;
    }
}
