<?php

declare(strict_types=1);

use RoundlyConsulting\Comments\Models\Comment;
use RoundlyConsulting\Comments\Models\CommentLock;
use RoundlyConsulting\Comments\Models\CommentMention;
use RoundlyConsulting\Comments\Models\CommentReaction;
use RoundlyConsulting\Comments\Tests\ActorTestModel;
use RoundlyConsulting\Comments\Tests\PostTestModel;

it('relates a reaction to its comment and reactor', function (): void {
    $post = PostTestModel::create();
    $user = ActorTestModel::create();
    $comment = Comment::factory()->for($post, 'commentable')->create();

    $reaction = CommentReaction::query()->create([
        'comment_id' => $comment->getKey(),
        'reactor_id' => $user->getKey(),
        'reactor_type' => $user->getMorphClass(),
        'reaction' => '👍',
    ]);

    expect($reaction->comment->is($comment))->toBeTrue()
        ->and($reaction->reactor->is($user))->toBeTrue();
});

it('relates a mention to its comment and mentionable', function (): void {
    $post = PostTestModel::create();
    $user = ActorTestModel::create();
    $comment = Comment::factory()->for($post, 'commentable')->create();

    $mention = CommentMention::query()->create([
        'comment_id' => $comment->getKey(),
        'handle' => 'alice',
        'mentionable_id' => $user->getKey(),
        'mentionable_type' => $user->getMorphClass(),
    ]);

    expect($mention->comment->is($comment))->toBeTrue()
        ->and($mention->mentionable->is($user))->toBeTrue();
});

it('relates a lock to its lockable subject', function (): void {
    $post = PostTestModel::create();

    $lock = CommentLock::query()->create([
        'lockable_id' => $post->getKey(),
        'lockable_type' => $post->getMorphClass(),
    ]);

    expect($lock->lockable->is($post))->toBeTrue();
});

it('ships factories for the satellite models', function (): void {
    $post = PostTestModel::create();
    $comment = Comment::factory()->for($post, 'commentable')->create();

    $reaction = CommentReaction::factory()->create(['comment_id' => $comment->getKey()]);
    $mention = CommentMention::factory()->create(['comment_id' => $comment->getKey()]);
    $lock = CommentLock::factory()->create([
        'lockable_id' => $post->getKey(),
        'lockable_type' => $post->getMorphClass(),
    ]);

    expect($reaction)->toBeInstanceOf(CommentReaction::class)
        ->and($mention)->toBeInstanceOf(CommentMention::class)
        ->and($lock)->toBeInstanceOf(CommentLock::class);
});
