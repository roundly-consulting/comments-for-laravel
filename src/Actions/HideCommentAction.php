<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\Actions;

use RoundlyConsulting\Comments\Enums\CommentStatus;
use RoundlyConsulting\Comments\Events\CommentHidden;
use RoundlyConsulting\Comments\Models\Comment;

final class HideCommentAction
{
    public function execute(Comment $comment): Comment
    {
        $comment->update(['status' => CommentStatus::Hidden]);

        CommentHidden::dispatch($comment);

        return $comment;
    }
}
