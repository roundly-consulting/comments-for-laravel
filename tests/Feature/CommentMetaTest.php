<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use RoundlyConsulting\Comments\DataTransferObjects\WriteCommentData;
use RoundlyConsulting\Comments\Facades\Comments;
use RoundlyConsulting\Comments\Models\Comment;
use RoundlyConsulting\Comments\Tests\ActorTestModel;
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

/**
 * C-2 — writing metadata (the DTO, the builder) and reading it back (`Comment::meta()`).
 */
it('writes meta through the fluent builder', function (): void {
    $post = PostTestModel::create();

    $comment = Comments::on($post)
        ->as(ActorTestModel::create())
        ->body('Internal note')
        ->visible(false)
        ->meta(['tickets' => ['internal' => true, 'role' => 'agent']])
        ->post();

    expect($comment->fresh()->meta)->toEqual(['tickets' => ['internal' => true, 'role' => 'agent']]);
});

it('merges repeated builder meta calls, later top-level keys winning', function (): void {
    $comment = Comments::on(PostTestModel::create())
        ->body('Merged')
        ->meta(['source' => 'api', 'tickets' => ['role' => 'requester']])
        ->meta(['tickets' => ['role' => 'agent'], 'channel' => 'email'])
        ->post();

    expect($comment->fresh()->meta)->toEqual([
        'source' => 'api',
        'tickets' => ['role' => 'agent'],
        'channel' => 'email',
    ]);
});

it('writes meta through the DTO', function (): void {
    $comment = Comments::write(new WriteCommentData(
        commentable: PostTestModel::create(),
        body: 'From DTO',
        meta: ['channel' => 'web'],
    ));

    expect($comment->fresh()->meta)->toBe(['channel' => 'web']);
});

it('stores no meta when none is given', function (): void {
    $comment = Comments::on(PostTestModel::create())->body('Plain')->meta([])->post();

    expect($comment->fresh()->meta)->toBeNull();
});

it('keeps writing plain comments on a host that has not run the meta migration', function (): void {
    // A 1.0 host that upgraded but has not re-published migrations yet has no `meta` column.
    Schema::table('comments', function (Blueprint $table): void {
        $table->dropColumn('meta');
    });

    $comment = Comments::on(PostTestModel::create())->body('Still works')->post();

    expect($comment->exists)->toBeTrue()
        ->and($comment->meta())->toBe([]);
});

it('reads meta with the model helpers', function (): void {
    $comment = Comment::factory()->on(PostTestModel::create())->meta([
        'tickets' => ['internal' => false, 'role' => 'agent', 'channel' => null],
    ])->create()->fresh();

    expect($comment->meta())->toEqual(['tickets' => ['internal' => false, 'role' => 'agent', 'channel' => null]])
        ->and($comment->meta('tickets.role'))->toBe('agent')
        ->and($comment->meta('tickets.internal'))->toBeFalse()
        ->and($comment->meta('tickets.missing'))->toBeNull()
        ->and($comment->meta('tickets.missing', 'fallback'))->toBe('fallback')
        ->and($comment->hasMeta('tickets.role'))->toBeTrue()
        // A key holding null is present — the same rule as Comments::query()->whereMetaMissing().
        ->and($comment->hasMeta('tickets.channel'))->toBeTrue()
        ->and($comment->hasMeta('tickets.missing'))->toBeFalse()
        ->and($comment->hasMeta('other'))->toBeFalse();
});

it('reads an empty meta from a comment that has none', function (): void {
    $comment = Comment::factory()->on(PostTestModel::create())->create()->fresh();

    expect($comment->meta())->toBe([])
        ->and($comment->meta('tickets.role', 'none'))->toBe('none')
        ->and($comment->hasMeta('tickets'))->toBeFalse();
});

it('keeps the meta attribute readable next to the meta() helper', function (): void {
    $comment = Comment::factory()->on(PostTestModel::create())->meta(['a' => 1])->create()->fresh();

    expect($comment->meta)->toBe(['a' => 1])
        ->and($comment->toArray()['meta'])->toBe(['a' => 1]);
});

it('records meta on the fake', function (): void {
    $fake = Comments::fake();

    Comments::on(PostTestModel::create())->body('Faked')->meta(['channel' => 'sms'])->post();

    $fake->assertPosted(fn (Comment $comment): bool => $comment->meta('channel') === 'sms');
});
