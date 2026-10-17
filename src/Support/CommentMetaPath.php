<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\Support;

use RoundlyConsulting\Comments\Exceptions\InvalidCommentMetaPathException;

/**
 * Turns a dot-notation `meta` path (`tickets.role`) into the json column reference the query
 * builder compiles per driver (`meta->tickets->role`).
 *
 * The path is the only part of a meta filter that reaches SQL as text — the grammar quotes each
 * segment into the json path itself — so it is allow-listed, never escaped: lowercase letters,
 * digits and underscores, dot-separated. A digits-only segment is refused as well, because
 * Postgres compiles it as an array index (`->0`) while MySQL and SQLite read it as an object
 * key, so the same filter would match different rows per engine.
 *
 * @internal
 */
final class CommentMetaPath
{
    private const string PATTERN = '/^[a-z0-9_]+(\.[a-z0-9_]+)*\z/';

    /**
     * @throws InvalidCommentMetaPathException
     */
    public static function column(string $path): string
    {
        if (preg_match(self::PATTERN, $path) !== 1) {
            throw InvalidCommentMetaPathException::make($path);
        }

        $segments = explode('.', $path);

        foreach ($segments as $segment) {
            if (ctype_digit($segment)) {
                throw InvalidCommentMetaPathException::make($path);
            }
        }

        return 'meta->'.implode('->', $segments);
    }
}
