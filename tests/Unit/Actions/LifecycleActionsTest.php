<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Comments\Actions\ApproveCommentAction;
use RoundlyConsulting\Comments\Actions\DeleteCommentAction;
use RoundlyConsulting\Comments\Actions\HideCommentAction;
use RoundlyConsulting\Comments\Actions\RestoreCommentAction;
use RoundlyConsulting\Comments\Actions\UpdateCommentAction;
use RoundlyConsulting\Comments\DataTransferObjects\UpdateCommentData;
use RoundlyConsulting\Comments\Enums\CommentStatus;
use RoundlyConsulting\Comments\Events\CommentApproved;
use RoundlyConsulting\Comments\Events\CommentDeleted;
use RoundlyConsulting\Comments\Events\CommentHidden;
use RoundlyConsulting\Comments\Events\CommentUpdated;
use RoundlyConsulting\Comments\Exceptions\InvalidCommentBodyException;
use RoundlyConsulting\Comments\Models\Comment;
use RoundlyConsulting\Comments\Tests\ActorTestModel;
use RoundlyConsulting\Comments\Tests\PostTestModel;

function freshComment(CommentStatus $status = CommentStatus::Approved): Comment
{
    return Comment::factory()
        ->for(ActorTestModel::create(), 'actor')
        ->for(PostTestModel::create(), 'commentable')
        ->create(['status' => $status]);
}

it('updates a comment body and dispatches the updated event', function (): void {
    Event::fake();
    $comment = freshComment();

    $updated = app(UpdateCommentAction::class)->execute(
        new UpdateCommentData(comment: $comment, body: 'Edited body'),
    );

    expect($updated->comment)->toBe('Edited body');
    Event::assertDispatched(CommentUpdated::class);
});

it('rejects an empty update body', function (): void {
    $comment = freshComment();

    app(UpdateCommentAction::class)->execute(
        new UpdateCommentData(comment: $comment, body: '  '),
    );
})->throws(InvalidCommentBodyException::class);

it('rejects an over-length update body', function (): void {
    config()->set('comments.max_length', 5);
    $comment = freshComment();

    app(UpdateCommentAction::class)->execute(
        new UpdateCommentData(comment: $comment, body: 'way too long'),
    );
})->throws(InvalidCommentBodyException::class);

it('soft deletes a comment and dispatches the deleted event', function (): void {
    Event::fake();
    $comment = freshComment();

    app(DeleteCommentAction::class)->execute($comment);

    expect(Comment::query()->count())->toBe(0)
        ->and(Comment::withTrashed()->count())->toBe(1);
    Event::assertDispatched(CommentDeleted::class);
});

it('restores a trashed comment', function (): void {
    $comment = freshComment();
    $comment->delete();

    app(RestoreCommentAction::class)->execute($comment);

    expect(Comment::query()->count())->toBe(1);
});

it('approves a comment and dispatches the approved event', function (): void {
    Event::fake();
    $comment = freshComment(CommentStatus::Pending);

    app(ApproveCommentAction::class)->execute($comment);

    expect($comment->refresh()->status)->toBe(CommentStatus::Approved);
    Event::assertDispatched(CommentApproved::class);
});

it('hides a comment and dispatches the hidden event', function (): void {
    Event::fake();
    $comment = freshComment();

    app(HideCommentAction::class)->execute($comment);

    expect($comment->refresh()->status)->toBe(CommentStatus::Hidden);
    Event::assertDispatched(CommentHidden::class);
});
