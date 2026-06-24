<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Comments\Actions\WriteCommentAction;
use RoundlyConsulting\Comments\DataTransferObjects\WriteCommentData;
use RoundlyConsulting\Comments\Enums\CommentStatus;
use RoundlyConsulting\Comments\Events\CommentCreated;
use RoundlyConsulting\Comments\Exceptions\InvalidCommentBodyException;
use RoundlyConsulting\Comments\Models\Comment;
use RoundlyConsulting\Comments\Tests\ActorTestModel;
use RoundlyConsulting\Comments\Tests\PostTestModel;

it('persists a comment with the correct morphs', function (): void {
    $actor = ActorTestModel::create();
    $post = PostTestModel::create();

    $comment = app(WriteCommentAction::class)->execute(new WriteCommentData(
        commentable: $post,
        body: 'Hello',
        author: $actor,
    ));

    expect($comment)->toBeInstanceOf(Comment::class)
        ->and($comment->actor_id)->toBe($actor->getKey())
        ->and($comment->commentable_id)->toBe($post->getKey())
        ->and($comment->comment)->toBe('Hello');
});

it('allows anonymous comments with no author', function (): void {
    $post = PostTestModel::create();

    $comment = app(WriteCommentAction::class)->execute(new WriteCommentData(
        commentable: $post,
        body: 'Guest here',
    ));

    expect($comment->actor_id)->toBeNull()
        ->and($comment->actor_type)->toBeNull();
});

it('respects the visible flag', function (): void {
    $post = PostTestModel::create();

    $comment = app(WriteCommentAction::class)->execute(new WriteCommentData(
        commentable: $post,
        body: 'Hidden body',
        visible: false,
    ));

    expect($comment->visible)->toBeFalse();
});

it('approves immediately when approval is not required', function (): void {
    config()->set('comments.require_approval', false);
    $post = PostTestModel::create();

    $comment = app(WriteCommentAction::class)->execute(new WriteCommentData(
        commentable: $post,
        body: 'Body',
    ));

    expect($comment->status)->toBe(CommentStatus::Approved);
});

it('marks comments pending when approval is required', function (): void {
    config()->set('comments.require_approval', true);
    $post = PostTestModel::create();

    $comment = app(WriteCommentAction::class)->execute(new WriteCommentData(
        commentable: $post,
        body: 'Body',
    ));

    expect($comment->status)->toBe(CommentStatus::Pending);
});

it('dispatches the comment created event', function (): void {
    Event::fake();
    $post = PostTestModel::create();

    app(WriteCommentAction::class)->execute(new WriteCommentData(
        commentable: $post,
        body: 'Body',
    ));

    Event::assertDispatched(CommentCreated::class);
});

it('throws on an empty body', function (): void {
    $post = PostTestModel::create();

    app(WriteCommentAction::class)->execute(new WriteCommentData(
        commentable: $post,
        body: '   ',
    ));
})->throws(InvalidCommentBodyException::class);

it('throws on an over-length body', function (): void {
    config()->set('comments.max_length', 10);
    $post = PostTestModel::create();

    app(WriteCommentAction::class)->execute(new WriteCommentData(
        commentable: $post,
        body: str_repeat('a', 11),
    ));
})->throws(InvalidCommentBodyException::class);
