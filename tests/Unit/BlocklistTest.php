<?php

declare(strict_types=1);

use RoundlyConsulting\Comments\Support\Blocklist;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;

it('refuses a blank, non-string or non-list blocklist (strict config)', function (mixed $blocklist): void {
    config()->set('comments.blocklist', $blocklist);

    expect(fn () => (new Blocklist)->matches('spam'))
        ->toThrow(InvalidConfigurationException::class, 'comments.blocklist');
})->with([
    'a blank entry' => [['', 'spam']],
    'a non-string entry' => [['spam', 3]],
    'a string' => ['spam'],
]);

it('reads an absent blocklist as empty (strict config)', function (): void {
    config()->set('comments.blocklist', null);

    expect((new Blocklist)->matches('spam'))->toBeFalse();
});

it('treats single-character entries as plain words', function (): void {
    config()->set('comments.blocklist', ['x']);

    expect((new Blocklist)->matches('marks the x spot'))->toBeTrue()
        ->and((new Blocklist)->matches('nothing here'))->toBeFalse();
});

it('does not treat an alphanumeric-delimited string as a regex', function (): void {
    config()->set('comments.blocklist', ['a1a']);

    expect((new Blocklist)->matches('the a1a code'))->toBeTrue();
});

it('matches a delimited regex pattern', function (): void {
    config()->set('comments.blocklist', ['/sp[a4]m/i']);

    expect((new Blocklist)->matches('this is SP4M'))->toBeTrue();
});

it('returns false with no blocklist configured', function (): void {
    config()->set('comments.blocklist', []);

    expect((new Blocklist)->matches('anything goes'))->toBeFalse();
});
