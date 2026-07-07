<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\Support;

use Illuminate\Support\Collection;
use RoundlyConsulting\MediaLibrary\Models\Media;

/**
 * Replaces inline `[media:UUID]` / `[media:UUID|variant]` tokens in a comment
 * body with the referenced media's responsive `<img>` (images) or an `<a>` link
 * (other files).
 *
 * The renderer never resolves media itself: the caller passes the already-fetched
 * candidate media (the comment's own attachments bucket), so resolution stays a
 * single batched query and inline media can only ever point at media the comment
 * owns — never an arbitrary global UUID.
 */
final class CommentBodyMediaRenderer
{
    private const TOKEN_PATTERN = '/\[media:(?<uuid>[0-9a-fA-F-]{36})(?:\|(?<variant>[\w-]+))?\]/';

    /**
     * Extract the unique media UUIDs referenced by inline tokens in the given body,
     * in order of first appearance. Malformed tokens (anything that is not a
     * 36-char UUID) are ignored.
     *
     * @return list<string>
     */
    public function extractUuids(string $body): array
    {
        if (preg_match_all(self::TOKEN_PATTERN, $body, $matches) === 0) {
            return [];
        }

        return array_values(array_unique($matches['uuid']));
    }

    /**
     * Render every inline token in $body using the supplied candidate media.
     *
     * @param  Collection<int, Media>  $media  the comment's own attachment media
     */
    public function render(
        string $body,
        Collection $media,
        string $defaultVariant = '',
        string $onMissing = 'strip',
    ): string {
        $byUuid = $media->keyBy('uuid');

        return (string) preg_replace_callback(
            self::TOKEN_PATTERN,
            function (array $match) use ($byUuid, $defaultVariant, $onMissing): string {
                $uuid = $match['uuid'];
                $variant = ($match['variant'] ?? '') !== '' ? $match['variant'] : $defaultVariant;

                $media = $byUuid->get($uuid);

                if (! $media instanceof Media) {
                    return $onMissing === 'keep' ? $match[0] : '';
                }

                return $this->renderMedia($media, $variant);
            },
            $body,
        );
    }

    private function renderMedia(Media $media, string $variant): string
    {
        if ($media->isImage()) {
            if ($variant !== '') {
                return '<img src="'.e($media->getUrl($variant)).'" alt="'.e($media->name).'">';
            }

            return $media->responsiveImage('', ['alt' => $media->name]);
        }

        return '<a href="'.e($media->getUrl()).'">'.e($media->name).'</a>';
    }
}
