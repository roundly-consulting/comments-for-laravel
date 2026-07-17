<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use RoundlyConsulting\Comments\Actions\WriteCommentAction;
use RoundlyConsulting\Comments\CommentsServiceProvider;
use RoundlyConsulting\Comments\DataTransferObjects\WriteCommentData;
use RoundlyConsulting\Comments\Tests\ActorTestModel;
use RoundlyConsulting\Comments\Tests\PostTestModel;
use RoundlyConsulting\PackageToolkit\Enums\DatabaseDriver;
use RoundlyConsulting\Testing\Database\DriverMatrix;

$migrations = __DIR__.'/../../database/migrations';

/**
 * P — the publish-only guards. The fleet publishes migrations timestamped rather than
 * auto-loading them; doing both runs both copies and dies on a duplicate table (bug #5, on
 * three packages). `1` pins the file count so neither check can pass over an empty or
 * relocated directory.
 */
it('never auto-loads its migrations — the host publishes them', function (): void {
    expect(CommentsServiceProvider::class)->toNotAutoLoadMigrations();
});

it('publishes its migrations timestamp-injected into the host', function (): void {
    expect(CommentsServiceProvider::class)->toPublishMigrationsTimestamped('comments-migrations', 1);
});

/**
 * R — the real-engine proof, `toApplyOnConnection` only.
 *
 * `toRejectBrokenOrderOnConnection` is deliberately NOT adopted, and this is the settled
 * rule rather than an omission: the negative control reverses the migration list, and with
 * ONE file the reversed list is the same list. With no foreign-key edges Postgres has
 * nothing to refuse, so the assertion would fail by design — the check working correctly
 * against a shape it does not fit.
 *
 * M is skipped for the same reason, re-verified against the migration source rather than
 * the spec: the single migration declares no `constrained()`/`references()` edge and no
 * `Schema::table()` ALTER, so both halves of `assertRunnable()` (the FK half and the
 * ALTER-sorts-after-CREATE half, which is approvals #2) have nothing to pin.
 */
it('applies its migrations on postgres', function () use ($migrations): void {
    expect($migrations)->toApplyOnConnection('pgsql', migrations: 1);
})->skip(fn (): bool => ! test()->connectionAvailable('pgsql'), 'no postgres connection available');

/**
 * The driver-truth pin. It compares the env-DECLARED driver against what the connection
 * itself answers, so a "pgsql" leg that quietly stayed on SQLite — a decapitated
 * `defineEnvironment()`, a missing `TESTING_DB_DRIVER` — goes red here rather than passing
 * as a postgres run. It fires automatically, unlike reading a skip count by hand.
 */
it('runs on the driver the environment declares', function (): void {
    expect(DatabaseDriver::current())->toBe(DatabaseDriver::from(DriverMatrix::driver()));
});

/**
 * The comments table is polymorphic (commentable + author morphs) and self-referencing
 * (parent_id for threading). Pinning a threaded round-trip on whatever engine the leg
 * configured proves those columns are usable rather than merely creatable.
 */
it('round-trips a threaded comment on the configured engine', function (): void {
    $post = PostTestModel::create();
    $actor = ActorTestModel::create();

    $parent = $actor->writeComment(commentable: $post, comment: 'Parent');

    $reply = app(WriteCommentAction::class)->execute(new WriteCommentData(
        commentable: $post,
        body: 'Reply',
        author: $actor,
        parent: $parent,
    ));

    expect($reply->fresh()->parent_id)->toBe($parent->getKey())
        ->and($parent->fresh()->commentable_type)->toBe($post->getMorphClass())
        ->and($parent->fresh()->actor_type)->toBe($actor->getMorphClass())
        ->and(DB::connection()->getDriverName())->toBe(DriverMatrix::driver());
});
