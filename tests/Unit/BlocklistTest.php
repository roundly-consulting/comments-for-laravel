<?php

declare(strict_types=1);

use RoundlyConsulting\Comments\Support\Blocklist;

it('ignores empty entries', function (): void {
    config()->set('comments.blocklist', ['', 'spam']);

    expect((new Blocklist)->matches('hello world'))->toBeFalse()
        ->and((new Blocklist)->matches('spam'))->toBeTrue();
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
