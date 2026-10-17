<?php

declare(strict_types=1);

use RoundlyConsulting\Comments\Models\Comment;
use RoundlyConsulting\Comments\Tests\PostTestModel;

/**
 * C-1 — the `meta` json column (1.1.0): an additive migration, cast to an array.
 */
it('round-trips meta through the array cast', function (): void {
    $comment = Comment::factory()->on(PostTestModel::create())->create([
        'meta' => ['tickets' => ['internal' => true, 'role' => 'agent']],
    ]);

    $meta = $comment->fresh()->meta;

    // toEqual, not toBe: jsonb (Postgres) and MySQL json store objects with their keys
    // re-ordered, so only the content round-trips, never the key order.
    expect($meta)->toEqual(['tickets' => ['internal' => true, 'role' => 'agent']])
        ->and($meta['tickets']['internal'])->toBeTrue()
        ->and($meta['tickets']['role'])->toBe('agent');
});

it('leaves meta null on a comment written without it', function (): void {
    $comment = Comment::factory()->on(PostTestModel::create())->create();

    expect($comment->fresh()->meta)->toBeNull();
});
