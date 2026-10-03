<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\Concerns;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use RoundlyConsulting\Comments\Support\CommentBodyMediaRenderer;
use RoundlyConsulting\Comments\Support\CommentsConfig;
use RoundlyConsulting\MediaLibrary\Buckets\MediaBucket;
use RoundlyConsulting\MediaLibrary\Concerns\InteractsWithMedia;
use RoundlyConsulting\MediaLibrary\Models\Media;
use RoundlyConsulting\PackageToolkit\Support\Config;

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

        $accepted = CommentsConfig::acceptedMimeTypes();

        if ($accepted !== []) {
            $bucket->acceptsMimeTypes($accepted);
        }

        $maxFileSize = CommentsConfig::maxFileSize();

        if ($maxFileSize !== null) {
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

    /**
     * The URL of every attachment, each resolved by its own visibility — see
     * {@see self::resolveAttachmentUrl()}: private attachments get short-lived signed URLs.
     *
     * A `$variant` is served for each attachment that has it generated (an image's
     * `responsive-<width>`, say); any other attachment — a PDF, or an image the variant was never
     * generated for — gets its original instead, so one mixed list never throws.
     *
     * @return list<string>
     */
    public function attachmentUrls(string $variant = ''): array
    {
        return array_values(
            $this->attachments()
                ->map(fn (Media $media): string => $this->resolveAttachmentUrl($media, $variant))
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
        return $media->getTemporaryUrl($this->temporaryUrlExpiry(), $this->servableVariant($media, $variant));
    }

    /**
     * The URL to serve an attachment at, chosen by the attachment's own visibility: a public
     * attachment gets its public (CDN-rewritable) URL, a private one a short-lived signed URL
     * ({@see self::attachmentUrl()}). A private attachment never gets a public URL — this is
     * the resolver `attachmentUrls()`, `renderBody()` and `CommentResource` all go through.
     *
     * A variant the attachment has not generated falls back to the original rather than throwing.
     */
    public function resolveAttachmentUrl(Media $media, string $variant = ''): string
    {
        $variant = $this->servableVariant($media, $variant);

        return $media->isPrivate()
            ? $this->attachmentUrl($media, $variant)
            : $media->getUrl($variant);
    }

    /**
     * The variant to serve for an attachment: the requested one when it was generated for this
     * attachment, otherwise the original (`''`). media-library throws for a variant it never
     * generated — every non-image, and any name the bucket does not declare.
     */
    private function servableVariant(Media $media, string $variant): string
    {
        return $variant !== '' && $media->hasGeneratedVariant($variant) ? $variant : '';
    }

    /**
     * Render the comment body, expanding inline `[media:UUID]` / `[media:UUID|variant]`
     * tokens into responsive images / links from the comment's own attachments bucket.
     *
     * Resolution is a single batched query over the referenced UUIDs and never throws
     * on a missing/unauthorized UUID (it is stripped or kept per
     * `comments.media.inline.on_missing`), nor on a variant the image lacks (the token's
     * `|variant` is the author's to choose; an ungenerated one renders the responsive image).
     * When inline rendering is disabled the raw body is returned unchanged.
     *
     * SECURITY WARNING — the output is NOT escaped (XSS risk). The comment text around the
     * tokens is returned exactly as stored; only the generated `<img>` / `<a>` markup is
     * escaped. The result is an `HtmlString`, so Blade prints it raw even inside `{{ }}` —
     * `{{ $comment->renderBody() }}` is exactly as unsafe as `{!! $comment->renderBody() !!}`.
     * Never output it for user-supplied comments unless the text is known to be safe:
     *
     *  - plain text: echo the attribute instead — `{{ $comment->comment }}` (Blade escapes it);
     *  - text + inline media: escape the text when you WRITE it
     *    (`Comments::on($post)->body(e($input))->post()` — tokens survive `e()`), or pass the
     *    output through an HTML sanitizer that only allows the generated `<img>` / `<a>` tags.
     */
    public function renderBody(): HtmlString
    {
        $body = (string) $this->getAttribute('comment');

        if (! Config::boolean('comments.media.inline.enabled', true)) {
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
            fn (Media $media, string $variant): string => $this->resolveAttachmentUrl($media, $variant),
            CommentsConfig::inlineDefaultVariant(),
            $this->onMissingStrategy(),
        );

        return new HtmlString($rendered);
    }

    public function attachmentsBucket(): string
    {
        return CommentsConfig::attachmentsBucket();
    }

    private function attachmentsVisibility(): string
    {
        return CommentsConfig::attachmentsVisibility();
    }

    private function configureMediaBucket(MediaBucket $bucket): MediaBucket
    {
        $disk = CommentsConfig::disk();

        if ($disk !== null) {
            $bucket->useDisk($disk);
        } elseif ($this->attachmentsVisibility() === CommentsConfig::VISIBILITY_PRIVATE) {
            // A private attachment must not land on media-library's default disk: that is the
            // web-served `public` disk, where the file is reachable under /storage without the
            // signed URL. Its variants follow it, whatever `media.variants_disk` says.
            $privateDisk = CommentsConfig::privateDisk();

            $bucket->useDisk($privateDisk)->storingVariantsOnDisk($privateDisk);
        }

        $bucket->responsiveWidths(CommentsConfig::responsiveWidths());

        return $bucket;
    }

    private function temporaryUrlExpiry(): DateTimeInterface
    {
        return CarbonImmutable::now()->addMinutes(CommentsConfig::temporaryUrlLifetime());
    }

    private function onMissingStrategy(): string
    {
        return CommentsConfig::inlineOnMissing();
    }
}
