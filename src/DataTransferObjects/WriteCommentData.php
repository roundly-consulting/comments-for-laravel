<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\DataTransferObjects;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Comments\Models\Comment;

final readonly class WriteCommentData
{
    /**
     * @param  array<string, mixed>  $meta  free-form metadata stored in the comment's `meta`
     *                                      json column; empty stores nothing (the column stays
     *                                      null), so a host without the 1.1.0 migration can
     *                                      still write plain comments
     */
    public function __construct(
        public Model $commentable,
        public string $body,
        public ?Model $author = null,
        public bool $visible = true,
        public ?Comment $parent = null,
        public array $meta = [],
    ) {}
}
