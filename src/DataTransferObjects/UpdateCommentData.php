<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\DataTransferObjects;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Comments\Models\Comment;

final readonly class UpdateCommentData
{
    /**
     * @param  Model|null  $editor  who made the edit, recorded on the revision while
     *                              `comments.edit_history` is on; null records none
     */
    public function __construct(
        public Comment $comment,
        public string $body,
        public ?Model $editor = null,
    ) {}
}
