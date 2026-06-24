<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use RoundlyConsulting\Comments\Models\Comment;

final class CommentUpdated
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public Comment $comment) {}
}
