<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use RoundlyConsulting\Comments\Models\Comment;
use RoundlyConsulting\Comments\Tests\PostTestModel;

/**
 * Pins the documented contract of `renderBody()`: it does NOT escape the comment text. It
 * returns an HtmlString, so even Blade's escaping `{{ }}` prints it raw — echoing it for
 * untrusted input is an XSS hole unless the host sanitizes first. If this ever changes, it
 * must be a deliberate change to the documented contract, not a silent one.
 */
beforeEach(function (): void {
    Storage::fake('public');
    config()->set('comments.media.visibility', 'public');
});

$payload = '<script>alert(1)</script><img src=x onerror=alert(2)>';

it('returns the stored text unescaped', function (bool $inline) use ($payload): void {
    config()->set('comments.media.inline.enabled', $inline);

    $comment = Comment::factory()->for(PostTestModel::create(), 'commentable')->create(['comment' => $payload]);

    expect($comment->renderBody())->toBeInstanceOf(HtmlString::class)
        ->and((string) $comment->renderBody())->toBe($payload);
})->with(['inline rendering on' => true, 'inline rendering off' => false]);

it('is not escaped by blade echo braces either', function () use ($payload): void {
    $comment = Comment::factory()->for(PostTestModel::create(), 'commentable')->create(['comment' => $payload]);

    expect(Blade::render('{{ $comment->renderBody() }}', ['comment' => $comment]))->toBe($payload);
});

it('leaves the raw attribute to blade escaping, the documented safe path for text', function () use ($payload): void {
    $comment = Comment::factory()->for(PostTestModel::create(), 'commentable')->create(['comment' => $payload]);

    expect(Blade::render('{{ $comment->comment }}', ['comment' => $comment]))->toBe(e($payload));
});

it('keeps inline tokens working on a body escaped at write time', function (): void {
    // The documented recipe for rendering untrusted bodies with inline media: escape the text
    // when it is written. Tokens carry no HTML-special characters, so they survive e().
    $comment = Comment::factory()->for(PostTestModel::create(), 'commentable')->create();
    $media = $comment->addMedia(UploadedFile::fake()->create('brief.pdf', 12, 'application/pdf'))
        ->toBucket($comment->attachmentsBucket());

    $comment->update(['comment' => e("<b>hi</b> [media:{$media->uuid}]")]);

    expect((string) $comment->renderBody())
        ->toBe('&lt;b&gt;hi&lt;/b&gt; <a href="'.e($media->getUrl()).'">brief</a>');
});

it('escapes what it generates from attachment metadata', function (): void {
    $comment = Comment::factory()->for(PostTestModel::create(), 'commentable')->create();
    $media = $comment->addMedia(UploadedFile::fake()->create('brief.pdf', 12, 'application/pdf'))
        ->usingName('"><script>alert(3)</script>')
        ->toBucket($comment->attachmentsBucket());

    $comment->update(['comment' => "[media:{$media->uuid}]"]);

    expect((string) $comment->renderBody())->not->toContain('<script>')
        ->and((string) $comment->renderBody())->toContain(e('"><script>alert(3)</script>'));
});
