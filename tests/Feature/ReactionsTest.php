<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Comments\Events\CommentReacted;
use RoundlyConsulting\Comments\Events\CommentUnreacted;
use RoundlyConsulting\Comments\Models\Comment;
use RoundlyConsulting\Comments\Models\CommentReaction;
use RoundlyConsulting\Comments\Tests\ActorTestModel;
use RoundlyConsulting\Comments\Tests\PostTestModel;

it('adds a reaction from a reactor', function (): void {
    $post = PostTestModel::create();
    $user = ActorTestModel::create();
    $comment = Comment::factory()->for($post, 'commentable')->create();

    $reaction = $comment->react('👍', as: $user);

    expect($reaction)->toBeInstanceOf(CommentReaction::class)
        ->and($reaction->reaction)->toBe('👍')
        ->and($reaction->reactor_id)->toBe($user->getKey());
});

it('allows anonymous reactions', function (): void {
    $post = PostTestModel::create();
    $comment = Comment::factory()->for($post, 'commentable')->create();

    $comment->react('❤️');

    expect($comment->reactions()->count())->toBe(1);
});

it('does not duplicate the same reaction from the same reactor', function (): void {
    $post = PostTestModel::create();
    $user = ActorTestModel::create();
    $comment = Comment::factory()->for($post, 'commentable')->create();

    $comment->react('👍', as: $user);
    $comment->react('👍', as: $user);

    expect($comment->reactions()->count())->toBe(1);
});

it('dispatches CommentReacted only when newly created', function (): void {
    Event::fake([CommentReacted::class]);

    $post = PostTestModel::create();
    $user = ActorTestModel::create();
    $comment = Comment::factory()->for($post, 'commentable')->create();

    $comment->react('👍', as: $user);
    $comment->react('👍', as: $user);

    Event::assertDispatchedTimes(CommentReacted::class, 1);
});

it('removes a reaction and dispatches CommentUnreacted', function (): void {
    Event::fake([CommentUnreacted::class]);

    $post = PostTestModel::create();
    $user = ActorTestModel::create();
    $comment = Comment::factory()->for($post, 'commentable')->create();
    $comment->react('👍', as: $user);

    $removed = $comment->unreact('👍', as: $user);

    expect($removed)->toBeTrue()
        ->and($comment->reactions()->count())->toBe(0);

    Event::assertDispatched(CommentUnreacted::class);
});

it('returns false when unreacting something that was never reacted', function (): void {
    $post = PostTestModel::create();
    $comment = Comment::factory()->for($post, 'commentable')->create();

    expect($comment->unreact('👍'))->toBeFalse();
});

it('aggregates reaction counts keyed by emoji', function (): void {
    $post = PostTestModel::create();
    $comment = Comment::factory()->for($post, 'commentable')->create();
    $a = ActorTestModel::create();
    $b = ActorTestModel::create();

    $comment->react('👍', as: $a);
    $comment->react('👍', as: $b);
    $comment->react('❤️', as: $a);

    expect($comment->reactionCounts())->toBe(['👍' => 2, '❤️' => 1]);
});

it('exposes comment and reactor relations on a reaction', function (): void {
    $post = PostTestModel::create();
    $user = ActorTestModel::create();
    $comment = Comment::factory()->for($post, 'commentable')->create();
    $reaction = $comment->react('👍', as: $user);

    expect($reaction->comment->is($comment))->toBeTrue()
        ->and($reaction->reactor->is($user))->toBeTrue();
});
