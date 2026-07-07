<?php

declare(strict_types=1);

use RoundlyConsulting\Comments\Enums\CommentStatus;

it('exposes the enum helper surface', function (): void {
    expect(CommentStatus::values()->all())->toBe(['pending', 'approved', 'hidden'])
        ->and(CommentStatus::labels()->all())->toBe(['Pending', 'Approved', 'Hidden'])
        ->and(CommentStatus::options()->count())->toBe(3)
        ->and(CommentStatus::validationRule())->toBe('in:pending,approved,hidden')
        ->and(CommentStatus::Hidden->readable())->toBe('Hidden');
});

it('supports case comparison helpers', function (): void {
    expect(CommentStatus::Approved->is(CommentStatus::Approved))->toBeTrue()
        ->and(CommentStatus::Approved->isIn([CommentStatus::Pending, CommentStatus::Hidden]))->toBeFalse()
        ->and(CommentStatus::Hidden->isIn([CommentStatus::Approved, CommentStatus::Hidden]))->toBeTrue();
});
