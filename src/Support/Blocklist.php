<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\Support;

use RoundlyConsulting\Comments\Enums\CommentStatus;
use RoundlyConsulting\Comments\Exceptions\CommentRejectedException;

final class Blocklist
{
    /**
     * The status a blocklisted body is held at, per `comments.blocklist_action`: `pending` or
     * `hidden`, `null` for a clean body. The `reject` action (and any unknown one) throws.
     * Writing and editing both ask here, so an edit cannot slip a payload past the filter.
     *
     * @throws CommentRejectedException
     */
    public function heldStatus(string $body): ?CommentStatus
    {
        if (! $this->matches($body)) {
            return null;
        }

        return match (CommentsConfig::blocklistAction()) {
            'pending' => CommentStatus::Pending,
            'hidden' => CommentStatus::Hidden,
            default => throw CommentRejectedException::blocked(),
        };
    }

    /**
     * Whether the body matches any configured banned word or pattern. Plain
     * strings match case-insensitively as whole words; entries that look like
     * a delimited regex (e.g. "/foo/i") are used as patterns.
     */
    public function matches(string $body): bool
    {
        foreach (CommentsConfig::blocklist() as $entry) {
            if ($this->isRegex($entry)) {
                if (preg_match($entry, $body) === 1) {
                    return true;
                }

                continue;
            }

            if (preg_match('/\b'.preg_quote($entry, '/').'\b/iu', $body) === 1) {
                return true;
            }
        }

        return false;
    }

    private function isRegex(string $entry): bool
    {
        if (mb_strlen($entry) < 2) {
            return false;
        }

        $delimiter = $entry[0];

        if (ctype_alnum($delimiter) || $delimiter === '\\' || ctype_space($delimiter)) {
            return false;
        }

        return @preg_match($entry, '') !== false;
    }
}
