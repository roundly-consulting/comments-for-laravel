<?php

declare(strict_types=1);

use RoundlyConsulting\Comments\DataTransferObjects\UpdateCommentData;
use RoundlyConsulting\Comments\DataTransferObjects\WriteCommentData;
use RoundlyConsulting\Comments\Models\Comment;
use RoundlyConsulting\Comments\Tests\ActorTestModel;
use RoundlyConsulting\Comments\Tests\PostTestModel;

it('exposes its values with sensible defaults', function (): void {
    $post = PostTestModel::create();

    $data = new WriteCommentData(commentable: $post, body: 'Hello');

    expect($data->commentable)->toBe($post)
        ->and($data->body)->toBe('Hello')
        ->and($data->author)->toBeNull()
        ->and($data->visible)->toBeTrue()
        ->and($data->parent)->toBeNull();
});

it('carries an author and parent when provided', function (): void {
    $actor = ActorTestModel::create();
    $post = PostTestModel::create();
    $parent = Comment::factory()->for($actor, 'actor')->for($post, 'commentable')->create();

    $data = new WriteCommentData(
        commentable: $post,
        body: 'Reply',
        author: $actor,
        visible: false,
        parent: $parent,
    );

    expect($data->author)->toBe($actor)
        ->and($data->parent)->toBe($parent)
        ->and($data->visible)->toBeFalse();
});

it('builds an update data object', function (): void {
    $comment = Comment::factory()
        ->for(ActorTestModel::create(), 'actor')
        ->for(PostTestModel::create(), 'commentable')
        ->create();

    $data = new UpdateCommentData(comment: $comment, body: 'New body');

    expect($data->comment)->toBe($comment)
        ->and($data->body)->toBe('New body');
});
