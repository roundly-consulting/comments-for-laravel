<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Comments\Events\CommentCreated;
use RoundlyConsulting\Comments\Tests\ActorTestModel;
use RoundlyConsulting\Comments\Tests\PostTestModel;

it('dispatches comment created events after writing comment', function () {
    /** @var ActorTestModel $actor */
    $actor = ActorTestModel::create();

    /** @var PostTestModel $post */
    $post = PostTestModel::create();

    Event::fake();

    $actor->writeComment(
        commentable: $post,
        comment: 'Shhhhh.. he may be listening',
    );

    Event::assertDispatched(function (CommentCreated $event) {
        return $event->comment->comment === 'Shhhhh.. he may be listening';
    });
});

it('returns actor from comment instance', function () {
    /** @var ActorTestModel $actor */
    $actor = ActorTestModel::create();

    /** @var PostTestModel $post */
    $post = PostTestModel::create();

    $comment = $actor->writeComment(
        commentable: $post,
        comment: 'It can list comments',
    );

    expect($comment->actor)
        ->toBeInstanceOf(ActorTestModel::class)
        ->id->toBe($actor->id);
});

it('returns commentable from comment instance', function () {
    /** @var ActorTestModel $actor */
    $actor = ActorTestModel::create();

    /** @var PostTestModel $post */
    $post = PostTestModel::create();

    $comment = $actor->writeComment(
        commentable: $post,
        comment: 'It can list comments',
    );

    expect($comment->commentable)
        ->toBeInstanceOf(PostTestModel::class)
        ->id->toBe($post->id);
});
