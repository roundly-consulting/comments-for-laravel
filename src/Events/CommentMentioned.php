<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use RoundlyConsulting\Comments\Models\CommentMention;

final class CommentMentioned
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public CommentMention $mention) {}
}
