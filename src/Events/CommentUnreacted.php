<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use RoundlyConsulting\Comments\Models\Comment;

final class CommentUnreacted
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public Comment $comment,
        public string $reaction,
        public ?Model $reactor = null,
    ) {}
}
