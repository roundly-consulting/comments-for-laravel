<?php

declare(strict_types=1);

use Illuminate\Foundation\Auth\User;
use RoundlyConsulting\Comments\Facades\Comments;
use RoundlyConsulting\Comments\Models\CommentLock;
use RoundlyConsulting\Comments\Tests\PostTestModel;
use RoundlyConsulting\Comments\Tests\UlidKeyedTestModel;
use RoundlyConsulting\Comments\Tests\UserTestModel;
use RoundlyConsulting\Comments\Tests\UuidKeyedTestModel;

/**
 * A bare lock used to name a fixed `post` #1: a subject that does not exist, an integer a
 * uuid column on Postgres refuses, a value no ulid column means, and a unique-key clash on
 * the second lock. It now locks a real subject, the same default a bare comment gets: a new
 * user of the configured auth model, built through that model's own factory.
 */
beforeEach(function (): void {
    config()->set('auth.providers.users.model', UserTestModel::class);
});

dataset('lock key types', [
    'uuid' => ['uuid', UuidKeyedTestModel::class],
    'ulid' => ['ulid', UlidKeyedTestModel::class],
]);

it('locks a fresh user of the auth model', function (): void {
    $lock = CommentLock::factory()->create();

    expect($lock->lockable)->toBeInstanceOf(UserTestModel::class)
        ->and($lock->lockable_type)->toBe((new UserTestModel)->getMorphClass())
        ->and(Comments::isLocked($lock->lockable))->toBeTrue();
});

it('gives each bare lock its own subject', function (): void {
    CommentLock::factory()->count(2)->create();

    expect(CommentLock::query()->count())->toBe(2)
        ->and(UserTestModel::query()->count())->toBe(2);
});

it('locks a user keyed by the configured key type', function (string $keyType, string $model): void {
    migrateCommentsKeyedBy($keyType, $model);
    config()->set('auth.providers.users.model', $model);

    $lock = CommentLock::factory()->create();

    expect($lock->fresh()?->lockable_type)->toBe((new $model)->getMorphClass());

    $subject = $model::query()->sole();

    expect($lock->fresh()?->lockable_id)->toBe($subject->getKey())
        ->and($lock->lockable?->is($subject))->toBeTrue()
        ->and(Comments::isLocked($subject))->toBeTrue();
})->with('lock key types');

it('locks the subject it is given', function (string $keyType, string $model, Closure $build): void {
    migrateCommentsKeyedBy($keyType, $model);
    $subject = $model::query()->create();

    $lock = $build($subject);

    expect($lock->fresh()?->lockable_id)->toBe($subject->getKey())
        ->and(Comments::isLocked($subject))->toBeTrue()
        ->and($model::query()->count())->toBe(1)
        ->and(UserTestModel::query()->count())->toBe(0);
})->with('lock key types')->with([
    'on()' => fn (UuidKeyedTestModel|UlidKeyedTestModel $subject): CommentLock => CommentLock::factory()->on($subject)->create(),
    'for()' => fn (UuidKeyedTestModel|UlidKeyedTestModel $subject): CommentLock => CommentLock::factory()->for($subject, 'lockable')->create(),
    'attributes' => fn (UuidKeyedTestModel|UlidKeyedTestModel $subject): CommentLock => CommentLock::factory()->create([
        'lockable_type' => $subject->getMorphClass(),
        'lockable_id' => $subject->getKey(),
    ]),
]);

it('locks a bigint subject it is given', function (): void {
    $post = PostTestModel::query()->create();

    CommentLock::factory()->on($post)->create();

    expect(Comments::isLocked($post))->toBeTrue()
        ->and(UserTestModel::query()->count())->toBe(0);
});

it('asks for a subject when the auth model has no factory', function (): void {
    config()->set('auth.providers.users.model', User::class);

    expect(fn (): CommentLock => CommentLock::factory()->create())
        ->toThrow(LogicException::class, 'CommentLock::factory()->on($subject)');

    expect(CommentLock::query()->count())->toBe(0);
});
