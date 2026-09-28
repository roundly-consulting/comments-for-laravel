<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\Actions;

use RoundlyConsulting\Comments\Enums\CommentStatus;
use RoundlyConsulting\Comments\Events\CommentApproved;
use RoundlyConsulting\Comments\Models\Comment;
use RoundlyConsulting\Comments\Support\CommentAuthorizer;

final readonly class ApproveCommentAction
{
    public function __construct(
        private CommentAuthorizer $authorizer,
    ) {}

    public function execute(Comment $comment): Comment
    {
        $this->authorizer->authorize('moderate', [$comment]);

        $comment->update(['status' => CommentStatus::Approved]);

        CommentApproved::dispatch($comment);

        return $comment;
    }
}
