<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RoundlyConsulting\Comments\Models\Comment;
use RoundlyConsulting\Comments\Tests\PostTestModel;
use RoundlyConsulting\MediaLibrary\Contracts\HasMedia;
use RoundlyConsulting\MediaLibrary\Exceptions\FileUnacceptableForBucket;
use RoundlyConsulting\MediaLibrary\Facades\MediaLibrary;

beforeEach(function (): void {
    Storage::fake('public');
    config()->set('comments.media.visibility', 'public');
});

function commentWithAttachment(string $file = 'shot.jpg'): array
{
    $comment = Comment::factory()->for(PostTestModel::create(), 'commentable')->create();

    $media = $comment->addMedia(UploadedFile::fake()->image($file, 800, 600))
        ->toBucket($comment->attachmentsBucket());

    return [$comment, $media];
}

it('is a media owner', function (): void {
    $comment = Comment::factory()->for(PostTestModel::create(), 'commentable')->create();

    expect($comment)->toBeInstanceOf(HasMedia::class);
});

it('attaches, lists and reads attachment urls', function (): void {
    [$comment, $media] = commentWithAttachment();

    expect($comment->hasAttachments())->toBeTrue()
        ->and($comment->attachments())->toHaveCount(1)
        ->and($comment->attachmentUrls())->toHaveCount(1)
        ->and($comment->attachmentUrls()[0])->toContain($media->uuid);
});

it('mints a signed temporary url for an attachment', function (): void {
    [$comment, $media] = commentWithAttachment();

    expect($comment->attachmentUrl($media))->toBeString()->not->toBe('');
});

it('renders an inline image token as a responsive img tag', function (): void {
    [$comment, $media] = commentWithAttachment();

    $comment->update(['comment' => "before [media:{$media->uuid}] after"]);

    $html = (string) $comment->renderBody();

    expect($html)->toContain('<img')
        ->and($html)->toContain('srcset=');
});

it('renders a non-image token as a link', function (): void {
    $comment = Comment::factory()->for(PostTestModel::create(), 'commentable')->create();

    $media = $comment->addMedia(UploadedFile::fake()->create('brief.pdf', 12, 'application/pdf'))
        ->toBucket($comment->attachmentsBucket());

    $comment->update(['comment' => "see [media:{$media->uuid}] now"]);

    $html = (string) $comment->renderBody();

    expect($html)->toContain('<a href=')
        ->and($html)->toContain($media->name);
});

it('resolves multiple tokens in a single query', function (): void {
    $comment = Comment::factory()->for(PostTestModel::create(), 'commentable')->create();

    $one = $comment->addMedia(UploadedFile::fake()->image('1.jpg', 400, 300))->toBucket($comment->attachmentsBucket());
    $two = $comment->addMedia(UploadedFile::fake()->image('2.jpg', 400, 300))->toBucket($comment->attachmentsBucket());

    $comment->update(['comment' => "[media:{$one->uuid}] and [media:{$two->uuid}]"]);

    DB::enableQueryLog();
    DB::flushQueryLog();

    $comment->renderBody();

    expect(DB::getQueryLog())->toHaveCount(1);

    DB::disableQueryLog();
});

it('strips a token whose uuid is not owned by the comment', function (): void {
    $comment = Comment::factory()->for(PostTestModel::create(), 'commentable')->create();

    // Global media (not in the comment's bucket) must never resolve inline.
    $global = MediaLibrary::add(UploadedFile::fake()->image('global.jpg', 400, 300))->toBucket('attachments');

    $comment->update(['comment' => "a [media:{$global->uuid}] b"]);

    expect((string) $comment->renderBody())->toBe('a  b');
});

it('keeps a missing token when on_missing is keep', function (): void {
    config()->set('comments.media.inline.on_missing', 'keep');

    $comment = Comment::factory()->for(PostTestModel::create(), 'commentable')->create();
    $uuid = '11111111-1111-1111-1111-111111111111';

    $comment->update(['comment' => "a [media:{$uuid}] b"]);

    expect((string) $comment->renderBody())->toBe("a [media:{$uuid}] b");
});

it('returns the raw body when inline rendering is disabled', function (): void {
    config()->set('comments.media.inline.enabled', false);

    [$comment, $media] = commentWithAttachment();
    $body = "raw [media:{$media->uuid}] body";

    $comment->update(['comment' => $body]);

    expect((string) $comment->renderBody())->toBe($body);
});

it('honours an explicit variant in the inline url', function (): void {
    [$comment, $media] = commentWithAttachment();

    $comment->update(['comment' => "x [media:{$media->uuid}|responsive-320] y"]);

    $html = (string) $comment->renderBody();

    expect($html)->toContain('<img')
        ->and($html)->toContain('responsive-320');
});

it('applies the configured bucket constraints', function (): void {
    config()->set('comments.media.accepted_mime_types', ['image/jpeg']);
    config()->set('comments.media.max_file_size', 1024 * 1024);
    config()->set('comments.media.disk', 'public');
    config()->set('comments.media.responsive_widths', [200, 400]);

    [$comment, $media] = commentWithAttachment();

    expect($comment->hasAttachments())->toBeTrue()
        ->and($media->disk)->toBe('public');
});

it('refuses an attachment over the configured size limit', function (): void {
    config()->set('comments.media.max_file_size', 2048);

    commentWithAttachment();
})->throws(FileUnacceptableForBucket::class);

it('refuses an attachment outside the accepted mime types', function (): void {
    config()->set('comments.media.accepted_mime_types', ['application/pdf']);

    commentWithAttachment();
})->throws(FileUnacceptableForBucket::class);

it('leaves malformed tokens untouched', function (): void {
    $comment = Comment::factory()->for(PostTestModel::create(), 'commentable')->create();

    $comment->update(['comment' => 'keep [media:not-a-uuid] this']);

    expect((string) $comment->renderBody())->toBe('keep [media:not-a-uuid] this');
});

it('cascades media removal when the comment bucket is cleared', function (): void {
    [$comment] = commentWithAttachment();

    $comment->clearMediaBucket($comment->attachmentsBucket());

    expect($comment->fresh()->hasAttachments())->toBeFalse();
});
