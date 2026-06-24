<?php

declare(strict_types=1);

use RoundlyConsulting\Comments\Tests\ActorTestModel;
use RoundlyConsulting\Comments\Tests\CustomCommentTestModel;
use RoundlyConsulting\Comments\Tests\PostTestModel;

it('writes using a custom configured comment model', function (): void {
    config()->set('comments.model', CustomCommentTestModel::class);

    $actor = ActorTestModel::create();
    $post = PostTestModel::create();

    $comment = $actor->writeComment(commentable: $post, comment: 'Custom model');

    expect($comment)->toBeInstanceOf(CustomCommentTestModel::class);
});

it('lists written comments using a custom configured model', function (): void {
    config()->set('comments.model', CustomCommentTestModel::class);

    $actor = ActorTestModel::create();
    $post = PostTestModel::create();

    $actor->writeComment(commentable: $post, comment: 'Custom');

    expect($actor->writtenComments->first())->toBeInstanceOf(CustomCommentTestModel::class)
        ->and($post->comments->first())->toBeInstanceOf(CustomCommentTestModel::class);
});
