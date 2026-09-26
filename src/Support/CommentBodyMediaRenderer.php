<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\Support;

use Closure;
use Illuminate\Support\Collection;
use RoundlyConsulting\MediaLibrary\Models\Media;
use RoundlyConsulting\MediaLibrary\Variants\ResponsiveImageGenerator;

/**
 * Replaces inline `[media:UUID]` / `[media:UUID|variant]` tokens in a comment
 * body with the referenced media's responsive `<img>` (images) or an `<a>` link
 * (other files).
 *
 * The renderer never resolves media itself: the caller passes the already-fetched
 * candidate media (the comment's own attachments bucket), so resolution stays a
 * single batched query and inline media can only ever point at media the comment
 * owns — never an arbitrary global UUID.
 *
 * Every URL comes from the caller's resolver, so a private attachment is linked through a
 * short-lived signed URL and never through a public one.
 *
 * Only the generated tags are escaped: the text around the tokens is returned exactly as
 * stored (see the XSS warning on `HasCommentMedia::renderBody()`).
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
     * @param  Closure(Media, string): string  $url  resolves a media + variant to the URL to serve
     */
    public function render(
        string $body,
        Collection $media,
        Closure $url,
        string $defaultVariant = '',
        string $onMissing = 'strip',
    ): string {
        $byUuid = $media->keyBy('uuid');

        return (string) preg_replace_callback(
            self::TOKEN_PATTERN,
            function (array $match) use ($byUuid, $url, $defaultVariant, $onMissing): string {
                $uuid = $match['uuid'];
                $variant = ($match['variant'] ?? '') !== '' ? $match['variant'] : $defaultVariant;

                $media = $byUuid->get($uuid);

                if (! $media instanceof Media) {
                    return $onMissing === 'keep' ? $match[0] : '';
                }

                return $this->renderMedia($media, $variant, $url);
            },
            $body,
        );
    }

    /**
     * @param  Closure(Media, string): string  $url
     */
    private function renderMedia(Media $media, string $variant, Closure $url): string
    {
        if ($media->isImage()) {
            if ($variant !== '') {
                return '<img src="'.e($url($media, $variant)).'" alt="'.e($media->name).'">';
            }

            // Public media keep media-library's own tag (CDN rewriting included); its URLs
            // are public ones, which private media does not have.
            return $media->isPrivate()
                ? $this->privateResponsiveImage($media, $url)
                : $media->responsiveImage('', ['alt' => $media->name]);
        }

        return '<a href="'.e($url($media, '')).'">'.e($media->name).'</a>';
    }

    /**
     * The same `<img>` media-library's `responsiveImage()` builds — smallest generated width
     * as `src`, every width in `srcset`, the LQIP placeholder — with each URL resolved by the
     * caller (signed, for private media).
     *
     * @param  Closure(Media, string): string  $url
     */
    private function privateResponsiveImage(Media $media, Closure $url): string
    {
        $widths = app(ResponsiveImageGenerator::class)->generatedWidths($media);

        $attributes = [
            'src' => $url($media, $widths === [] ? '' : ResponsiveImageGenerator::variantName($widths[0])),
        ];

        if ($widths !== []) {
            $attributes['srcset'] = implode(', ', array_map(
                fn (int $width): string => $url($media, ResponsiveImageGenerator::variantName($width)).' '.$width.'w',
                $widths,
            ));
        }

        $attributes['alt'] = $media->name;

        $placeholder = $media->placeholderDataUri();

        if ($placeholder !== null) {
            $attributes['style'] = "background-size:cover;background-image:url('{$placeholder}')";
        }

        $rendered = '';

        foreach ($attributes as $name => $value) {
            $rendered .= ' '.$name.'="'.e($value).'"';
        }

        return '<img'.$rendered.'>';
    }
}
