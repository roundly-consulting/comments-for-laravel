<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * `Comments::transfer()` re-pointed every comment of `$from` at `$to` — `$count` of them, which
 * may be 0. Fired after the move, by the same process, like every comments event.
 */
final class CommentsTransferred
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public Model $from,
        public Model $to,
        public int $count,
    ) {}
}
