<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use RoundlyConsulting\Comments\Enums\CommentStatus;
use RoundlyConsulting\Comments\Exceptions\CommentRejectedException;
use RoundlyConsulting\Comments\Facades\Comments;
use RoundlyConsulting\Comments\Models\Comment;
use RoundlyConsulting\Comments\Support\Blocklist;
use RoundlyConsulting\Comments\Support\CommentsConfig;
use RoundlyConsulting\Comments\Tests\PostTestModel;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;

/**
 * Sweep 2 — the non-boolean settings. `(int) config()` read `max_length = five` as 0; an
 * `order` typo sorted newest first; an attachment visibility typo, a non-string bucket or disk
 * and a junk lifetime or width ladder quietly fell back. Each now throws, naming the key.
 *
 * Sweep 3 — a blank value (a host's `KEY=`, or whitespace) is not set: it takes the default,
 * exactly like an absent key. Junk still throws.
 */
beforeEach(function (): void {
    Storage::fake('public');
    Storage::fake('local');
});

function strictComment(): Comment
{
    return Comment::factory()->for(PostTestModel::create(), 'commentable')->create();
}

it('refuses a junk or non-positive body length (strict config)', function (mixed $value, string $message): void {
    config()->set('comments.max_length', $value);

    expect(fn () => Comments::on(PostTestModel::create())->body('hello')->post())
        ->toThrow(InvalidConfigurationException::class, $message);
})->with([
    'junk' => ['five', 'Configuration value [comments.max_length] must be an integer, [five] given.'],
    'decimal' => ['5.5', 'Configuration value [comments.max_length] must be an integer, [5.5] given.'],
    'zero' => [0, 'Configuration value [comments.max_length] must be at least 1, [0] given.'],
]);

it('reads a blank body length as not set, so the default applies (strict config)', function (string $blank): void {
    config()->set('comments.max_length', $blank);

    expect(CommentsConfig::maxLength())->toBe(5000)
        ->and(Comments::on(PostTestModel::create())->body(str_repeat('a', 5000))->post()->exists)->toBeTrue();
})->with(['empty' => [''], 'whitespace' => ['  ']]);

it('refuses a junk body length on edit too (strict config)', function (): void {
    $comment = Comments::on(PostTestModel::create())->body('hello')->post();
    config()->set('comments.max_length', '5.5');

    expect(fn () => Comments::update($comment, 'changed'))
        ->toThrow(InvalidConfigurationException::class, 'comments.max_length');
});

it('reads an integer string body length and the absent default (strict config)', function (): void {
    config()->set('comments.max_length', '5');

    expect(fn () => Comments::on(PostTestModel::create())->body('too long')->post())->toThrow('5');

    config()->set('comments.max_length', null);

    expect(Comments::on(PostTestModel::create())->body(str_repeat('a', 5000))->post()->exists)->toBeTrue();
});

it('refuses a junk or non-positive reply depth (strict config)', function (mixed $value, string $message): void {
    $root = Comments::on(PostTestModel::create())->body('root')->post();
    config()->set('comments.max_depth', $value);

    expect(fn () => Comments::on($root->commentable)->reply($root)->body('reply')->post())
        ->toThrow(InvalidConfigurationException::class, $message);
})->with([
    'junk' => ['deep', 'Configuration value [comments.max_depth] must be an integer, [deep] given.'],
    'zero' => [0, 'Configuration value [comments.max_depth] must be at least 1, [0] given.'],
]);

it('refuses a junk reply depth when eager-loading a thread (strict config)', function (): void {
    $post = PostTestModel::create();
    config()->set('comments.max_depth', 'deep');

    expect(fn () => $post->threadedComments()->get())->toThrow(InvalidConfigurationException::class, 'comments.max_depth');
});

it('refuses an order typo instead of sorting newest first (strict config)', function (): void {
    $post = PostTestModel::create();
    config()->set('comments.order', 'olderst');

    expect(fn () => $post->approvedComments()->get())->toThrow(
        InvalidConfigurationException::class,
        'Configuration value [comments.order] must be one of [latest, oldest], [olderst] given.',
    );
});

it('refuses a blocklist action typo instead of rejecting (strict config)', function (): void {
    config()->set('comments.blocklist', ['casino']);
    config()->set('comments.blocklist_action', 'hide');

    expect(fn () => (new Blocklist)->heldStatus('visit my casino'))->toThrow(
        InvalidConfigurationException::class,
        'Configuration value [comments.blocklist_action] must be one of [reject, pending, hidden], [hide] given.',
    );
});

it('uses reject when the blocklist action is absent (strict config)', function (): void {
    config()->set('comments.blocklist', ['casino']);
    config()->set('comments.blocklist_action', null);

    expect(fn () => (new Blocklist)->heldStatus('visit my casino'))->toThrow(CommentRejectedException::class);
});

it('holds a blocklisted comment per a valid action (strict config)', function (): void {
    config()->set('comments.blocklist', ['casino']);
    config()->set('comments.blocklist_action', 'hidden');

    expect((new Blocklist)->heldStatus('visit my casino'))->toBe(CommentStatus::Hidden);
});

it('refuses an attachment visibility typo (strict config)', function (mixed $value): void {
    config()->set('comments.media.visibility', $value);

    expect(fn () => strictComment()->resolveMediaBucket('attachments'))->toThrow(
        InvalidConfigurationException::class,
        'Configuration value [comments.media.visibility] must be one of [private, public]',
    );
})->with(['typo' => ['pubilc'], 'capitalised' => ['Public']]);

it('keeps attachments private when the visibility is absent or blank (strict config)', function (?string $value): void {
    config()->set('comments.media.visibility', $value);

    expect(strictComment()->resolveMediaBucket('attachments')?->getVisibility())->toBe('private');
})->with(['absent' => [null], 'blank' => [''], 'whitespace' => [' ']]);

it('refuses a non-string media storage setting (strict config)', function (string $key, mixed $value): void {
    config()->set($key, $value);

    expect(fn () => strictComment()->resolveMediaBucket('attachments'))
        ->toThrow(InvalidConfigurationException::class, "Configuration value [{$key}] must be a non-empty string");
})->with([
    'bucket array' => ['comments.media.attachments_bucket', ['a']],
    'disk int' => ['comments.media.disk', 1],
    'private disk bool' => ['comments.media.private_disk', true],
]);

it('reads a blank media storage setting as not set (strict config)', function (): void {
    config()->set('comments.media.attachments_bucket', '');
    config()->set('comments.media.disk', '');
    config()->set('comments.media.private_disk', ' ');

    $bucket = strictComment()->resolveMediaBucket('attachments');

    expect(CommentsConfig::attachmentsBucket())->toBe('attachments')
        ->and(CommentsConfig::disk())->toBeNull()
        ->and($bucket?->getDisk())->toBe('local')
        ->and($bucket?->getVariantsDisk())->toBe('local');
});

it('reads a blank optional media setting as not set, so it stays off (strict config)', function (): void {
    config()->set('comments.media.max_file_size', '');
    config()->set('comments.media.responsive_widths', ' ');
    config()->set('comments.media.temporary_url_lifetime', '');
    config()->set('media.temporary_url_default_lifetime', 7);
    config()->set('comments.media.accepted_mime_types', '');
    config()->set('comments.media.inline.default_variant', ' ');

    expect(CommentsConfig::maxFileSize())->toBeNull()
        ->and(CommentsConfig::responsiveWidths())->toBeNull()
        ->and(CommentsConfig::temporaryUrlLifetime())->toBe(7)
        ->and(CommentsConfig::acceptedMimeTypes())->toBe([])
        ->and(CommentsConfig::inlineDefaultVariant())->toBe('');
});

it('reads a blank blocklist, resolver or resolved action as not set (strict config)', function (): void {
    config()->set('comments.blocklist', '');
    config()->set('comments.mention_resolver', '');
    config()->set('comments.moderation.on_resolved', ' ');

    expect(CommentsConfig::blocklist())->toBe([])
        ->and(CommentsConfig::mentionResolver())->toBeNull()
        ->and(CommentsConfig::onResolved())->toBeNull();
});

it('refuses a junk media list or size (strict config)', function (string $key, mixed $value): void {
    config()->set($key, $value);

    expect(fn () => strictComment()->resolveMediaBucket('attachments'))
        ->toThrow(InvalidConfigurationException::class, $key);
})->with([
    'mime types string' => ['comments.media.accepted_mime_types', 'image/jpeg'],
    'mime types junk entry' => ['comments.media.accepted_mime_types', ['image/jpeg', 5]],
    'max file size junk' => ['comments.media.max_file_size', '1MB'],
    'max file size zero' => ['comments.media.max_file_size', 0],
    'widths string' => ['comments.media.responsive_widths', '200,400'],
    'widths junk entry' => ['comments.media.responsive_widths', [200, 'wide']],
]);

it('reads integer strings for the size and widths (strict config)', function (): void {
    config()->set('comments.media.max_file_size', '2048');
    config()->set('comments.media.responsive_widths', ['200', 400]);

    $bucket = strictComment()->resolveMediaBucket('attachments');

    expect($bucket?->getMaxFileSize())->toBe(2048)
        ->and($bucket?->getResponsiveWidths())->toBe([200, 400]);
});

it('refuses a junk signed-url lifetime (strict config)', function (string $key, mixed $value): void {
    $comment = strictComment();
    $comment->addMedia(UploadedFile::fake()->image('a.jpg', 400, 300))->toBucket('attachments');
    config()->set($key, $value);

    expect(fn () => $comment->attachmentUrls())->toThrow(InvalidConfigurationException::class, $key);
})->with([
    'comment lifetime junk' => ['comments.media.temporary_url_lifetime', 'soon'],
    'comment lifetime zero' => ['comments.media.temporary_url_lifetime', 0],
    'media default junk' => ['media.temporary_url_default_lifetime', 'soon'],
]);

it('refuses an inline on_missing typo or a non-string default variant (strict config)', function (string $key, mixed $value): void {
    $comment = strictComment();
    $comment->update(['comment' => 'x [media:9b2f6c1e-0000-4000-8000-000000000000] y']);
    config()->set($key, $value);

    expect(fn () => (string) $comment->renderBody())->toThrow(InvalidConfigurationException::class, $key);
})->with([
    'on_missing typo' => ['comments.media.inline.on_missing', 'kepe'],
    'default variant array' => ['comments.media.inline.default_variant', ['thumb']],
]);

it('flags a broken setting in about instead of rendering a fallback (strict config)', function (): void {
    config()->set('comments.max_length', 'lots');
    config()->set('comments.max_depth', 'deep');

    Artisan::call('about', ['--only' => 'comments']);
    $output = Artisan::output();

    expect($output)->toMatch('/Max length\W+INVALID/')
        ->and($output)->toMatch('/Max depth\W+INVALID/')
        ->and($output)->not->toContain('0 chars');
});

it('names the variant expectation for a non-string default variant (strict config)', function (): void {
    config()->set('comments.media.inline.default_variant', ['thumb']);

    expect(fn () => CommentsConfig::inlineDefaultVariant())->toThrow(
        InvalidConfigurationException::class,
        "Configuration value [comments.media.inline.default_variant] must be a variant name (a string, '' for the original), [array] given.",
    );
});
