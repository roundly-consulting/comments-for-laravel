<?php

declare(strict_types=1);

use RoundlyConsulting\Comments\Models\Comment;
use RoundlyConsulting\Comments\Tests\ActorTestModel;
use RoundlyConsulting\Comments\Tests\PostTestModel;

it('writes comment to entity', function () {
    /** @var ActorTestModel $actor */
    $actor = ActorTestModel::create();

    /** @var PostTestModel $post */
    $post = PostTestModel::create();

    $comment = $actor->writeComment(
        commentable: $post,
        comment: 'Very good blog post',
    );

    $model = config('comments.model', Comment::class);

    expect($comment)
        ->toBeInstanceOf($model);

    $this->assertDatabaseHas('comments', [
        'commentable_id' => $post->getKey(),
        'commentable_type' => $post->getMorphClass(),
        'actor_id' => $actor->getKey(),
        'actor_type' => $actor->getMorphClass(),
        'comment' => 'Very good blog post',
        'visible' => true,
    ]);
});

it('list all comments given by actor', function () {
    /** @var ActorTestModel $actor */
    $actor = ActorTestModel::create();

    /** @var PostTestModel $post */
    $post = PostTestModel::create();

    $actor->writeComment(
        commentable: $post,
        comment: 'It can list comments',
    );

    $comments = $actor->writtenComments;

    $model = config('comments.model', Comment::class);

    expect($comments)
        ->toBeCollection()
        ->toHaveCount(1)
        ->and($comments->first())
        ->toBeInstanceOf($model)
        ->comment->toBe('It can list comments');
});
