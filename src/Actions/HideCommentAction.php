<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\Actions;

use RoundlyConsulting\Comments\Enums\CommentStatus;
use RoundlyConsulting\Comments\Events\CommentHidden;
use RoundlyConsulting\Comments\Models\Comment;
use RoundlyConsulting\Comments\Support\CommentAuthorizer;

final readonly class HideCommentAction
{
    public function __construct(
        private CommentAuthorizer $authorizer,
    ) {}

    public function execute(Comment $comment): Comment
    {
        $this->authorizer->authorize('moderate', [$comment]);

        $comment->update(['status' => CommentStatus::Hidden]);

        CommentHidden::dispatch($comment);

        return $comment;
    }
}
