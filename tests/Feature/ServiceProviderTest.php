<?php

declare(strict_types=1);

use Illuminate\Support\ServiceProvider;
use RoundlyConsulting\Comments\CommentManager;

it('registers all publish tags', function (string $tag): void {
    $paths = ServiceProvider::pathsToPublish(null, $tag);

    expect($paths)->not->toBeEmpty();
})->with([
    'comments-config',
    'comments-migrations',
    'comments-translations',
]);

it('loads the package translations', function (): void {
    expect(__('comments::comments.body_empty'))
        ->toBe('A comment body cannot be empty.');
});

it('binds the comment manager as a singleton', function (): void {
    expect(app(CommentManager::class))->toBe(app(CommentManager::class));
});

it('contributes a comments section to about', function (string $expected): void {
    config()->set('comments.max_length', 1234);

    $this->artisan('about --only=comments')
        ->expectsOutputToContain($expected)
        ->assertExitCode(0);
})->with([
    'Comments',
    'Model',
    'Require approval',
    '1234 chars',
    'Auto-moderation',
    'Inline media',
]);

it('reports the blocklist as a count and never leaks a blocked term', function (): void {
    config()->set('comments.blocklist', ['hunter2secret', 'anotherterm']);

    $this->artisan('about --only=comments')
        ->expectsOutputToContain('2 term(s)')
        ->doesntExpectOutputToContain('hunter2secret')
        ->assertExitCode(0);
});
