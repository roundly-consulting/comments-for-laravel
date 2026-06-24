<?php

declare(strict_types=1);

use RoundlyConsulting\Comments\Enums\CommentStatus;
use RoundlyConsulting\Comments\Models\Comment;
use RoundlyConsulting\Comments\Tests\ActorTestModel;
use RoundlyConsulting\Comments\Tests\PostTestModel;

it('returns only visible approved top-level comments', function (): void {
    $actor = ActorTestModel::create();
    $post = PostTestModel::create();

    Comment::factory()->for($actor, 'actor')->for($post, 'commentable')
        ->create(['comment' => 'visible', 'status' => CommentStatus::Approved, 'visible' => true]);
    Comment::factory()->for($actor, 'actor')->for($post, 'commentable')
        ->create(['comment' => 'pending', 'status' => CommentStatus::Pending]);
    Comment::factory()->for($actor, 'actor')->for($post, 'commentable')
        ->create(['comment' => 'hidden', 'status' => CommentStatus::Hidden]);
    Comment::factory()->for($actor, 'actor')->for($post, 'commentable')
        ->create(['comment' => 'invisible', 'status' => CommentStatus::Approved, 'visible' => false]);

    $approved = $post->approvedComments;

    expect($approved)->toHaveCount(1)
        ->and($approved->first()->comment)->toBe('visible');
});

it('excludes replies from approved comments', function (): void {
    $actor = ActorTestModel::create();
    $post = PostTestModel::create();

    $root = $actor->writeComment(commentable: $post, comment: 'root');
    Comment::factory()->for($actor, 'actor')->for($post, 'commentable')
        ->create(['parent_id' => $root->getKey()]);

    expect($post->approvedComments)->toHaveCount(1);
});

it('orders approved comments oldest first when configured', function (): void {
    config()->set('comments.order', 'oldest');

    $actor = ActorTestModel::create();
    $post = PostTestModel::create();

    $first = Comment::factory()->for($actor, 'actor')->for($post, 'commentable')
        ->create(['comment' => 'first', 'created_at' => now()->subDay()]);
    Comment::factory()->for($actor, 'actor')->for($post, 'commentable')
        ->create(['comment' => 'second', 'created_at' => now()]);

    expect($post->approvedComments->first()->comment)->toBe('first');
});

it('orders approved comments latest first by default', function (): void {
    $actor = ActorTestModel::create();
    $post = PostTestModel::create();

    Comment::factory()->for($actor, 'actor')->for($post, 'commentable')
        ->create(['comment' => 'old', 'created_at' => now()->subDay()]);
    Comment::factory()->for($actor, 'actor')->for($post, 'commentable')
        ->create(['comment' => 'new', 'created_at' => now()]);

    expect($post->approvedComments->first()->comment)->toBe('new');
});
