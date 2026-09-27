<?php

declare(strict_types=1);

use RoundlyConsulting\Comments\Models\Comment;
use RoundlyConsulting\Comments\Tests\ActorTestModel;
use RoundlyConsulting\Comments\Tests\PostTestModel;

it('list comments to entity', function () {
    /** @var ActorTestModel $actor */
    $actor = ActorTestModel::create();

    /** @var PostTestModel $post */
    $post = PostTestModel::create();

    $actor->writeComment(
        commentable: $post,
        comment: 'It can list comments from entity',
    );

    $comments = $post->comments;

    $model = config('comments.model', Comment::class);

    expect($comments)
        ->toBeCollection()
        ->toHaveCount(1)
        ->and($comments->first())
        ->toBeInstanceOf($model)
        ->comment->toBe('It can list comments from entity');
});

it('list comments to entity with replies', function () {
    /** @var ActorTestModel $actor */
    $actor = ActorTestModel::create();

    /** @var PostTestModel $post */
    $post = PostTestModel::create();

    $comment = $actor->writeComment(
        commentable: $post,
        comment: 'It can list comments from entity with replies',
    );

    $comment = $actor->writeComment(
        commentable: $comment,
        comment: 'WOW! Thats great.',
    );

    $actor->writeComment(
        commentable: $comment,
        comment: 'Sure it is.',
    );

    $comments = $post->comments;

    $model = config('comments.model', Comment::class);

    expect($comments)
        ->toBeCollection()
        ->toHaveCount(1)
        ->and($comments->first())
        ->toBeInstanceOf($model)
        ->comment
        ->toBe('It can list comments from entity with replies')
        ->and($comments->first()->comments)
        ->toBeCollection()
        ->toHaveCount(1)
        ->and($comments->first()->comments->first())
        ->toBeInstanceOf($model)
        ->comment
        ->toBe('WOW! Thats great.')
        ->and($comments->first()->comments->first()->comments)
        ->toBeCollection()
        ->toHaveCount(1)
        ->and($comments->first()->comments->first()->comments->first())
        ->toBeInstanceOf($model)
        ->comment
        ->toBe('Sure it is.');
});
