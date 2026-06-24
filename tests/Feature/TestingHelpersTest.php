<?php

declare(strict_types=1);

use RoundlyConsulting\Comments\Enums\CommentStatus;
use RoundlyConsulting\Comments\Models\Comment;
use RoundlyConsulting\Comments\Testing\AssertsComments;
use RoundlyConsulting\Comments\Tests\ActorTestModel;
use RoundlyConsulting\Comments\Tests\PostTestModel;

uses(AssertsComments::class);

it('exposes factory states for pending, hidden, locked, reply and by', function (): void {
    $post = PostTestModel::create();
    $user = ActorTestModel::create();

    $pending = Comment::factory()->for($post, 'commentable')->pending()->create();
    $hidden = Comment::factory()->for($post, 'commentable')->hidden()->create();
    $locked = Comment::factory()->for($post, 'commentable')->locked()->create();
    $root = Comment::factory()->for($post, 'commentable')->by($user)->create();
    $reply = Comment::factory()->reply($root)->create();

    expect($pending->status)->toBe(CommentStatus::Pending)
        ->and($hidden->status)->toBe(CommentStatus::Hidden)
        ->and($locked->isLocked())->toBeTrue()
        ->and($root->actor_id)->toBe($user->getKey())
        ->and($reply->parent_id)->toBe($root->getKey())
        ->and($reply->commentable_id)->toBe($post->getKey());
});

it('asserts a subject has been commented on', function (): void {
    $post = PostTestModel::create();
    Comment::factory()->for($post, 'commentable')->create(['comment' => 'great post']);

    $this->assertCommented($post);
    $this->assertCommented($post, 'great post');
    $this->assertCommentCount($post, 1);
});

it('asserts a subject has no comments', function (): void {
    $post = PostTestModel::create();

    $this->assertNotCommented($post);
    $this->assertCommentCount($post, 0);
});
