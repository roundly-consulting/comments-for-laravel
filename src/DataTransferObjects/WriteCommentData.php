<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\DataTransferObjects;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Comments\Models\Comment;

final readonly class WriteCommentData
{
    public function __construct(
        public Model $commentable,
        public string $body,
        public ?Model $author = null,
        public bool $visible = true,
        public ?Comment $parent = null,
    ) {}
}
