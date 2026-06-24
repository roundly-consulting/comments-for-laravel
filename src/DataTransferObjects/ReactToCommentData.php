<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\DataTransferObjects;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Comments\Models\Comment;

final readonly class ReactToCommentData
{
    public function __construct(
        public Comment $comment,
        public string $reaction,
        public ?Model $reactor = null,
    ) {}
}
