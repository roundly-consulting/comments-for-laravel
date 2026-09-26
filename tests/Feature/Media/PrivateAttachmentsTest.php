<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use RoundlyConsulting\Comments\Http\Resources\CommentResource;
use RoundlyConsulting\Comments\Models\Comment;
use RoundlyConsulting\Comments\Tests\PostTestModel;
use RoundlyConsulting\MediaLibrary\Models\Media;

/**
 * Private attachments — the shipped default (`comments.media.visibility` = private) — are
 * only ever served through short-lived signed URLs. Every URL surface used to ask media for
 * the attachment's PUBLIC URL (`getUrl()`), which media refuses for private media, so on the
 * default config `attachmentUrls()`, an inline token in `renderBody()` and the resource's
 * `attachments` array all threw instead of serving the file.
 */
beforeEach(function (): void {
    Storage::fake('public');
    Storage::fake('local');
    $this->freezeSecond();
});

function privateCommentWith(UploadedFile $file): array
{
    $comment = Comment::factory()->for(PostTestModel::create(), 'commentable')->create();

    $media = $comment->addMedia($file)->toBucket($comment->attachmentsBucket());

    return [$comment, $media];
}

/** Every URL the public disk would publish for this media (original + generated variants). */
function publicDiskUrls(Media $media): array
{
    $urls = [Storage::disk('public')->url($media->getPath())];

    foreach (array_keys($media->generated_variants ?? []) as $variant) {
        $urls[] = Storage::disk('public')->url($media->getPath((string) $variant));
    }

    return $urls;
}

it('ships private attachments by default', function (): void {
    [, $media] = privateCommentWith(UploadedFile::fake()->create('brief.pdf', 12, 'application/pdf'));

    expect(config('comments.media.visibility'))->toBe('private')
        ->and($media->isPrivate())->toBeTrue();
});

it('lists signed urls for private attachments', function (): void {
    [$comment, $media] = privateCommentWith(UploadedFile::fake()->create('brief.pdf', 12, 'application/pdf'));

    $urls = $comment->attachmentUrls();

    expect($urls)->toBe([$comment->attachmentUrl($media)])
        ->and($urls[0])->not->toBeIn(publicDiskUrls($media));
});

it('resolves a private attachment to a signed url and a public one to its public url', function (): void {
    [$comment, $private] = privateCommentWith(UploadedFile::fake()->create('brief.pdf', 12, 'application/pdf'));

    // The bucket's visibility is read when a model instance registers its buckets, so the
    // public attachment goes onto a fresh instance.
    config()->set('comments.media.visibility', 'public');
    $public = Comment::query()->findOrFail($comment->getKey())
        ->addMedia(UploadedFile::fake()->create('open.pdf', 12, 'application/pdf'))
        ->toBucket($comment->attachmentsBucket());

    expect($public->isPrivate())->toBeFalse()
        ->and($comment->resolveAttachmentUrl($private))->toBe($comment->attachmentUrl($private))
        ->and($comment->resolveAttachmentUrl($public))->toBe($public->getUrl())
        ->and($comment->attachmentUrls())->toBe([$comment->attachmentUrl($private), $public->getUrl()]);
});

it('renders a private inline image with signed urls only', function (): void {
    [$comment, $media] = privateCommentWith(UploadedFile::fake()->image('shot.jpg', 800, 600));
    $comment->update(['comment' => "look [media:{$media->uuid}] here"]);

    $html = (string) $comment->renderBody();

    expect($html)->toContain('<img')
        ->and($html)->toContain('srcset=')
        ->and($html)->toContain(e($comment->attachmentUrl($media, 'responsive-320')))
        ->and($html)->toContain(e($comment->attachmentUrl($media, 'responsive-640')));

    foreach (publicDiskUrls($media) as $publicUrl) {
        expect($html)->not->toContain($publicUrl.'"')
            ->and($html)->not->toContain($publicUrl.' ');
    }
});

it('renders a private inline image variant with a signed url', function (): void {
    [$comment, $media] = privateCommentWith(UploadedFile::fake()->image('shot.jpg', 800, 600));
    $comment->update(['comment' => "x [media:{$media->uuid}|responsive-320] y"]);

    expect((string) $comment->renderBody())
        ->toBe('x <img src="'.e($comment->attachmentUrl($media, 'responsive-320')).'" alt="shot"> y');
});

it('renders a private inline file as a signed link', function (): void {
    [$comment, $media] = privateCommentWith(UploadedFile::fake()->create('brief.pdf', 12, 'application/pdf'));
    $comment->update(['comment' => "see [media:{$media->uuid}] now"]);

    expect((string) $comment->renderBody())
        ->toBe('see <a href="'.e($comment->attachmentUrl($media)).'">brief</a> now');
});

it('exposes signed urls for private attachments in the resource', function (): void {
    [$comment, $media] = privateCommentWith(UploadedFile::fake()->create('brief.pdf', 12, 'application/pdf'));
    $comment->load('media');

    $array = (new CommentResource($comment))->toArray(Request::create('/'));

    expect($array['attachments'])->toBe([
        ['id' => $media->uuid, 'url' => $comment->attachmentUrl($media)],
    ]);
});

/*
 * Signed URLs only protect the link. Private attachments used to be written to media-library's
 * default disk — the web-served `public` disk — so the file itself sat under /storage for anyone
 * with the path. They now default to `comments.media.private_disk` ('local').
 */

it('stores private attachments and their variants on the private disk', function (): void {
    [, $media] = privateCommentWith(UploadedFile::fake()->image('shot.jpg', 800, 600));

    expect($media->disk)->toBe('local')
        ->and($media->diskFor('responsive-320'))->toBe('local')
        ->and(Storage::disk('local')->exists($media->getPath()))->toBeTrue()
        ->and(Storage::disk('local')->exists($media->getPath('responsive-320')))->toBeTrue()
        ->and(Storage::disk('public')->allFiles())->toBe([]);
});

it('keeps private variants off a globally configured public variants disk', function (): void {
    config()->set('media.variants_disk', 'public');

    [, $media] = privateCommentWith(UploadedFile::fake()->image('shot.jpg', 800, 600));

    expect($media->diskFor('responsive-320'))->toBe('local')
        ->and(Storage::disk('public')->allFiles())->toBe([]);
});

it('honours a configured private disk', function (): void {
    config()->set('filesystems.disks.vault', ['driver' => 'local', 'root' => storage_path('app/vault')]);
    Storage::fake('vault');
    config()->set('comments.media.private_disk', 'vault');

    [, $media] = privateCommentWith(UploadedFile::fake()->create('brief.pdf', 12, 'application/pdf'));

    expect($media->disk)->toBe('vault');
});

it('lets an explicit disk win and keeps public attachments on the media default disk', function (): void {
    config()->set('comments.media.visibility', 'public');
    [, $public] = privateCommentWith(UploadedFile::fake()->create('open.pdf', 12, 'application/pdf'));

    config()->set('comments.media.visibility', 'private');
    config()->set('comments.media.disk', 'public');
    [, $explicit] = privateCommentWith(UploadedFile::fake()->create('brief.pdf', 12, 'application/pdf'));

    expect($public->disk)->toBe('public')
        ->and($explicit->disk)->toBe('public');
});

it('serves a private attachment from the real private disk through the signed stream route', function (): void {
    // A real (not faked) local disk: no native temporary URLs, so media mints its own signed
    // streaming route — the path a default host's private attachments take. The route must
    // stream the bytes from the private disk, and refuse the link once it is tampered with.
    // The stream route sits behind the `web` group, whose cookie encryption needs a key.
    config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));

    $root = sys_get_temp_dir().'/comments-private-'.bin2hex(random_bytes(4));
    config()->set('filesystems.disks.local', ['driver' => 'local', 'root' => $root]);
    Storage::forgetDisk('local');

    try {
        [$comment, $media] = privateCommentWith(UploadedFile::fake()->createWithContent('brief.txt', 'private bytes'));

        $url = $comment->attachmentUrl($media);

        expect($media->disk)->toBe('local')
            ->and(is_file($root.'/'.$media->getPath()))->toBeTrue()
            ->and(URL::hasValidSignature(Request::create($url)))->toBeTrue();

        $response = $this->get($url);

        $response->assertOk();
        expect($response->streamedContent())->toBe('private bytes');

        $this->get($url.'0')->assertForbidden();

        expect(Storage::disk('public')->exists($media->getPath()))->toBeFalse();
    } finally {
        Storage::forgetDisk('local');
        File::deleteDirectory($root);
    }
});
