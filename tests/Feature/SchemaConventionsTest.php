<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;

/**
 * Pins the exact schema the migration emits — every column, index and morph
 * nullability — so a change to how the tables are declared can never silently
 * move a column or drop an index on host apps that already migrated.
 */
it('emits the comments table columns', function (): void {
    expect(Schema::getColumnListing('comments'))->toBe([
        'id',
        'visible',
        'status',
        'parent_id',
        'actor_type',
        'actor_id',
        'commentable_type',
        'commentable_id',
        'comment',
        'locked_at',
        'created_at',
        'updated_at',
        'deleted_at',
    ]);
});

it('emits the comment_mentions and comment_locks columns', function (): void {
    expect(Schema::getColumnListing('comment_mentions'))->toBe([
        'id',
        'comment_id',
        'handle',
        'mentionable_type',
        'mentionable_id',
        'created_at',
        'updated_at',
    ]);

    expect(Schema::getColumnListing('comment_locks'))->toBe([
        'id',
        'lockable_type',
        'lockable_id',
        'created_at',
        'updated_at',
    ]);
});

it('keeps the morph indexes', function (string $table, string $index): void {
    expect(Schema::hasIndex($table, $index))->toBeTrue();
})->with([
    ['comments', 'comments_actor_type_actor_id_index'],
    ['comments', 'comments_commentable_type_commentable_id_index'],
    ['comments', 'comments_commentable_type_commentable_id_status_index'],
    ['comment_mentions', 'comment_mentions_mentionable_type_mentionable_id_index'],
    ['comment_locks', 'comment_locks_lockable_type_lockable_id_index'],
    ['comment_locks', 'comment_locks_lockable_type_lockable_id_unique'],
]);

it('keeps morph nullability', function (string $table, string $column, bool $nullable): void {
    $columns = collect(Schema::getColumns($table))->keyBy('name');

    expect($columns[$column]['nullable'])->toBe($nullable);
})->with([
    // An actorless (guest) comment and an unresolved mention must still insert.
    ['comments', 'actor_id', true],
    ['comments', 'actor_type', true],
    ['comment_mentions', 'mentionable_id', true],
    ['comments', 'commentable_id', false],
    ['comments', 'commentable_type', false],
    ['comment_locks', 'lockable_id', false],
]);
