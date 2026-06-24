<?php

declare(strict_types=1);

use Illuminate\Support\ServiceProvider;

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
