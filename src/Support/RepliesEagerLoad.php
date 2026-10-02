<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\Support;

use Closure;
use Illuminate\Database\Eloquent\Relations\Relation;
use RoundlyConsulting\Comments\Enums\CommentStatus;

/**
 * The nested `replies` eager load behind `threadedComments()` and `CommentQuery::withReplies()`,
 * bounded by `comments.max_depth` (a root is depth 1, so `max_depth - 1` reply levels load).
 *
 * Each level is its own constrained load, so a public read can hold every level to the
 * `Comment::visible()` rule: a hidden, pending or `visible = false` reply never loads, and
 * neither does anything below it.
 *
 * @internal
 */
final class RepliesEagerLoad
{
    /**
     * @return array<string, Closure(Relation<*, *, *>): void>
     */
    public static function make(bool $publicOnly): array
    {
        $levels = max(1, (int) config('comments.max_depth', 5) - 1);

        return ['replies' => self::level($levels, $publicOnly)];
    }

    /**
     * @return Closure(Relation<*, *, *>): void
     */
    private static function level(int $remaining, bool $publicOnly): Closure
    {
        return static function (Relation $replies) use ($remaining, $publicOnly): void {
            if ($publicOnly) {
                $replies->getBaseQuery()->where('visible', true)->where('status', CommentStatus::Approved->value);
            }

            if ($remaining > 1) {
                $replies->with(['replies' => self::level($remaining - 1, $publicOnly)]);
            }
        };
    }
}
