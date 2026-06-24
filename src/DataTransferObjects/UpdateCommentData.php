<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\DataTransferObjects;

use RoundlyConsulting\Comments\Models\Comment;

final readonly class UpdateCommentData
{
    public function __construct(
        public Comment $comment,
        public string $body,
    ) {}
}
