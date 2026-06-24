<?php

declare(strict_types=1);

use RoundlyConsulting\Comments\Models\Comment;
use RoundlyConsulting\Comments\Tests\ActorTestModel;
use RoundlyConsulting\Comments\Tests\PostTestModel;

it('builds a comment from its factory', function (): void {
    $actor = ActorTestModel::create();
    $post = PostTestModel::create();

    $comment = Comment::factory()
        ->for($actor, 'actor')
        ->for($post, 'commentable')
        ->create();

    expect($comment)
        ->toBeInstanceOf(Comment::class)
        ->visible->toBeTrue()
        ->and($comment->comment)->toBeString();
});

it('casts the visible column to a boolean', function (): void {
    $actor = ActorTestModel::create();
    $post = PostTestModel::create();

    $comment = Comment::factory()
        ->for($actor, 'actor')
        ->for($post, 'commentable')
        ->create(['visible' => 0]);

    expect($comment->refresh()->visible)->toBeFalse();
});

it('soft deletes a comment', function (): void {
    $actor = ActorTestModel::create();
    $post = PostTestModel::create();

    $comment = Comment::factory()
        ->for($actor, 'actor')
        ->for($post, 'commentable')
        ->create();

    $comment->delete();

    expect(Comment::query()->count())->toBe(0)
        ->and(Comment::withTrashed()->count())->toBe(1);
});

it('resolves actor and commentable morph relations', function (): void {
    $actor = ActorTestModel::create();
    $post = PostTestModel::create();

    $comment = $actor->writeComment(commentable: $post, comment: 'Nice');

    expect($comment->actor)->toBeInstanceOf(ActorTestModel::class)
        ->and($comment->commentable)->toBeInstanceOf(PostTestModel::class);
});
