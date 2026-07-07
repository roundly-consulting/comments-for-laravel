<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\Concerns;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use RoundlyConsulting\Comments\Support\CommentBodyMediaRenderer;
use RoundlyConsulting\MediaLibrary\Buckets\MediaBucket;
use RoundlyConsulting\MediaLibrary\Concerns\InteractsWithMedia;
use RoundlyConsulting\MediaLibrary\Models\Media;

/**
 * First-class media for the bundled Comment model, built on
 * roundly-consulting/media-library-for-laravel.
 *
 * Declares the comment's single `attachments` bucket (image variants + signed
 * temporary streaming for other files) on top of media-library's
 * `InteractsWithMedia` seam, and adds an inline `[media:UUID]` body renderer that
 * expands tokens against the comment's own bucket.
 *
 * @mixin Model
 */
trait HasCommentMedia
{
    use InteractsWithMedia;

    public function registerMediaBuckets(): void
    {
        // Attachments hold inline media of any type (images render responsively,
        // other files render as links / signed downloads), so the bucket stays
        // mime-open unless the host narrows it via config.
        $bucket = $this->addMediaBucket($this->attachmentsBucket())
            ->withVisibility($this->attachmentsVisibility());

        $accepted = config('comments.media.accepted_mime_types');

        if (is_array($accepted) && $accepted !== []) {
            $bucket->acceptsMimeTypes($this->stringList($accepted));
        }

        $maxFileSize = config('comments.media.max_file_size');

        if (is_int($maxFileSize) && $maxFileSize > 0) {
            $bucket->maxFileSize($maxFileSize);
        }

        $this->configureMediaBucket($bucket);
    }

    /**
     * Every attachment on this comment (images and non-images), in bucket order.
     *
     * @return Collection<int, Media>
     */
    public function attachments(): Collection
    {
        return $this->getMedia($this->attachmentsBucket());
    }

    /** @return list<string> */
    public function attachmentUrls(string $variant = ''): array
    {
        return array_values(
            $this->attachments()
                ->map(fn (Media $media): string => $media->getUrl($variant))
                ->all(),
        );
    }

    public function hasAttachments(): bool
    {
        return $this->hasMedia($this->attachmentsBucket());
    }

    /**
     * A short-lived, signed URL for an attachment (presigned on capable disks,
     * otherwise via media's signed streaming route). Works for images and other
     * file types alike — useful for attachments on private/comment threads.
     */
    public function attachmentUrl(Media $media, string $variant = ''): string
    {
        return $media->getTemporaryUrl($this->temporaryUrlExpiry(), $variant);
    }

    /**
     * Render the comment body, expanding inline `[media:UUID]` / `[media:UUID|variant]`
     * tokens into responsive images / links from the comment's own attachments bucket.
     *
     * Resolution is a single batched query over the referenced UUIDs and never throws
     * on a missing/unauthorized UUID (it is stripped or kept per
     * `comments.media.inline.on_missing`). When inline rendering is disabled the raw
     * body is returned unchanged.
     */
    public function renderBody(): HtmlString
    {
        $body = (string) $this->getAttribute('comment');

        if (! (bool) config('comments.media.inline.enabled', true)) {
            return new HtmlString($body);
        }

        $renderer = app(CommentBodyMediaRenderer::class);
        $uuids = $renderer->extractUuids($body);

        /** @var Collection<int, Media> $media */
        $media = $uuids === []
            ? new Collection
            : $this->media()
                ->where('bucket_name', $this->attachmentsBucket())
                ->whereIn('uuid', $uuids)
                ->get()
                // The media is owned by this comment; hydrate the inverse relation so URL/path
                // generation does not lazily reload the owner once per token (avoids N+1).
                ->each(fn (Media $media): Media => $media->setRelation('model', $this));

        $rendered = $renderer->render(
            $body,
            $media,
            (string) config('comments.media.inline.default_variant', ''),
            $this->onMissingStrategy(),
        );

        return new HtmlString($rendered);
    }

    public function attachmentsBucket(): string
    {
        return (string) config('comments.media.attachments_bucket', 'attachments');
    }

    private function attachmentsVisibility(): string
    {
        $visibility = config('comments.media.visibility', 'private');

        return $visibility === 'public' ? 'public' : 'private';
    }

    private function configureMediaBucket(MediaBucket $bucket): MediaBucket
    {
        $disk = config('comments.media.disk');

        if (is_string($disk) && $disk !== '') {
            $bucket->useDisk($disk);
        }

        $widths = config('comments.media.responsive_widths');

        $bucket->responsiveWidths(
            is_array($widths) ? $this->normalizeWidths($widths) : null,
        );

        return $bucket;
    }

    private function temporaryUrlExpiry(): DateTimeInterface
    {
        $minutes = config('comments.media.temporary_url_lifetime');

        if (! is_numeric($minutes)) {
            $minutes = config('media.temporary_url_default_lifetime', 5);
        }

        return CarbonImmutable::now()->addMinutes(is_numeric($minutes) ? (int) $minutes : 5);
    }

    private function onMissingStrategy(): string
    {
        $strategy = config('comments.media.inline.on_missing', 'strip');

        return $strategy === 'keep' ? 'keep' : 'strip';
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @return list<string>
     */
    private function stringList(array $values): array
    {
        $clean = [];

        foreach ($values as $value) {
            if (is_string($value) && $value !== '') {
                $clean[] = $value;
            }
        }

        return $clean;
    }

    /**
     * @param  array<array-key, mixed>  $widths
     * @return list<int>
     */
    private function normalizeWidths(array $widths): array
    {
        $clean = [];

        foreach ($widths as $width) {
            if (is_int($width) && $width > 0) {
                $clean[] = $width;
            }
        }

        return $clean;
    }
}
