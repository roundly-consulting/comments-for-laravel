<?php

declare(strict_types=1);

use Illuminate\Support\ServiceProvider;
use RoundlyConsulting\Comments\CommentManager;
use RoundlyConsulting\Comments\CommentsServiceProvider;

it('registers all publish tags', function (string $tag): void {
    $paths = ServiceProvider::pathsToPublish(null, $tag);

    expect($paths)->not->toBeEmpty();
})->with([
    'comments-config',
    'comments-migrations',
    'comments-translations',
]);

it('never auto-loads its migrations', function (): void {
    $package = realpath(__DIR__.'/../../database/migrations');

    expect(array_map(realpath(...), app('migrator')->paths()))
        ->not->toContain($package);
});

it('publishes the migration into the host database path', function (): void {
    $paths = ServiceProvider::pathsToPublish(CommentsServiceProvider::class, 'comments-migrations');

    expect($paths)->toHaveCount(1);

    $source = (string) array_key_first($paths);
    $destination = (string) reset($paths);

    expect(basename($source))->toBe('0001_01_01_000000_create_comments_table.php')
        ->and($destination)->toMatch('#/database/migrations/\d{4}_\d{2}_\d{2}_\d{6}_create_comments_table\.php$#');
});

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
