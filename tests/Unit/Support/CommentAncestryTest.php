<?php

declare(strict_types=1);

use RoundlyConsulting\Comments\Models\Comment;
use RoundlyConsulting\Comments\Support\CommentAncestry;
use RoundlyConsulting\Comments\Tests\PostTestModel;

it('returns no parent for a root', function (): void {
    $root = Comment::factory()->for(PostTestModel::create(), 'commentable')->create();

    expect(app(CommentAncestry::class)->parentOf($root))->toBeNull()
        ->and(app(CommentAncestry::class)->depthOf($root))->toBe(1);
});

it('reads a soft-deleted parent without caching it on the relation', function (): void {
    $root = Comment::factory()->for(PostTestModel::create(), 'commentable')->create();
    $reply = Comment::factory()->reply($root)->create();
    $root->delete();

    $parent = app(CommentAncestry::class)->parentOf($reply);

    expect($parent?->is($root))->toBeTrue()
        ->and($parent?->trashed())->toBeTrue()
        ->and($reply->relationLoaded('parent'))->toBeFalse()
        ->and($reply->parent)->toBeNull();
});

it('reuses an already-loaded parent', function (): void {
    $root = Comment::factory()->for(PostTestModel::create(), 'commentable')->create();
    $reply = Comment::factory()->reply($root)->create();
    $reply->load('parent');

    expect(app(CommentAncestry::class)->parentOf($reply))->toBe($reply->parent);
});

it('still counts the link to a force-deleted parent', function (): void {
    $root = Comment::factory()->for(PostTestModel::create(), 'commentable')->create();
    $reply = Comment::factory()->reply($root)->create();
    $root->forceDelete();

    expect(app(CommentAncestry::class)->depthOf($reply))->toBe(2);
});

it('ends the depth walk on a cycle', function (): void {
    $a = Comment::factory()->for(PostTestModel::create(), 'commentable')->create();
    $b = Comment::factory()->reply($a)->create();
    $a->update(['parent_id' => $b->getKey()]);

    expect(app(CommentAncestry::class)->depthOf($b->fresh()))->toBe(3);
});
