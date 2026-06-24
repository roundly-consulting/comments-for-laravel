<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\Actions;

use RoundlyConsulting\Comments\Enums\CommentStatus;
use RoundlyConsulting\Comments\Events\CommentApproved;
use RoundlyConsulting\Comments\Models\Comment;

final class ApproveCommentAction
{
    public function execute(Comment $comment): Comment
    {
        $comment->update(['status' => CommentStatus::Approved]);

        CommentApproved::dispatch($comment);

        return $comment;
    }
}
