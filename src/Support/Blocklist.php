<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\Support;

final class Blocklist
{
    /**
     * Whether the body matches any configured banned word or pattern. Plain
     * strings match case-insensitively as whole words; entries that look like
     * a delimited regex (e.g. "/foo/i") are used as patterns.
     */
    public function matches(string $body): bool
    {
        /** @var list<string> $entries */
        $entries = config('comments.blocklist', []);

        foreach ($entries as $entry) {
            if ($entry === '') {
                continue;
            }

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
