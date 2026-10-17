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
 * three packages). `2` pins the file count — the frozen `create_comments_table` plus the
 * additive `add_meta_to_comments_table` (1.1.0) — so neither check can pass over an empty or
 * relocated directory.
 */
it('never auto-loads its migrations — the host publishes them', function (): void {
    expect(CommentsServiceProvider::class)->toNotAutoLoadMigrations();
});

it('publishes its migrations timestamp-injected into the host', function (): void {
    expect(CommentsServiceProvider::class)->toPublishMigrationsTimestamped('comments-migrations', 2);
});

/**
 * M — the structural order pin. The set has no foreign-key edge (`parent_id` and
 * `comment_id` are plain indexed columns), so `foreignKeys: 0` is the honest count, and the
 * guard-the-guard still compares it with the raw `constrained()`/`references()` occurrences.
 * What makes the pin non-vacuous is the ALTER half: `add_meta_to_comments_table` runs a
 * `Schema::table('comments')`, which must sort after the CREATE that makes the table.
 */
it('orders its migrations so every alter runs after its create', function () use ($migrations): void {
    expect($migrations)->toHaveRunnableMigrationOrder(foreignKeys: 0);
});

/**
 * R — the real-engine proof. With two files the negative control fits: the reversed list
 * runs the `meta` ALTER before the table exists, which Postgres and MySQL both refuse. (With
 * the single 1.0 file the reversed list was the same list, so it could not be adopted.)
 */
$alterBeforeCreate = fn (array $files): array => array_reverse($files);

it('applies its migrations on postgres', function () use ($migrations): void {
    expect($migrations)->toApplyOnConnection('pgsql', migrations: 2);
})->skip(fn (): bool => ! test()->connectionAvailable('pgsql'), 'no postgres connection available');

it('rejects an alter-before-create order on postgres', function () use ($migrations, $alterBeforeCreate): void {
    expect($migrations)->toRejectBrokenOrderOnConnection($alterBeforeCreate, 'pgsql');
})->skip(fn (): bool => ! test()->connectionAvailable('pgsql'), 'no postgres connection available');

it('applies its migrations on mysql', function () use ($migrations): void {
    expect($migrations)->toApplyOnConnection('mysql', migrations: 2);
})->skip(fn (): bool => ! test()->connectionAvailable('mysql'), 'no mysql connection available');

it('rejects an alter-before-create order on mysql', function () use ($migrations, $alterBeforeCreate): void {
    expect($migrations)->toRejectBrokenOrderOnConnection($alterBeforeCreate, 'mysql');
})->skip(fn (): bool => ! test()->connectionAvailable('mysql'), 'no mysql connection available');

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
