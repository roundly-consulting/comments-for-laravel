<?php

declare(strict_types=1);

use PHPUnit\Framework\AssertionFailedError;
use RoundlyConsulting\Comments\CommentsManager;
use RoundlyConsulting\Comments\DataTransferObjects\WriteCommentData;
use RoundlyConsulting\Comments\Exceptions\CommentsLockedException;
use RoundlyConsulting\Comments\Facades\Comments;
use RoundlyConsulting\Comments\Models\Comment;
use RoundlyConsulting\Comments\Testing\CommentsFake;
use RoundlyConsulting\Comments\Tests\PostTestModel;
use RoundlyConsulting\Comments\Tests\UserTestModel;

it('swaps a recording fake behind the facade and the container', function (): void {
    $fake = Comments::fake();

    expect($fake)->toBeInstanceOf(CommentsFake::class)
        ->and(app(CommentsManager::class))->toBe($fake)
        ->and(Comments::getFacadeRoot())->toBe($fake);
});

it('still performs every operation it records', function (): void {
    Comments::fake();
    $post = PostTestModel::create();

    $comment = Comments::on($post)->body('Hi')->post();

    expect($post->comments()->count())->toBe(1)
        ->and($comment->exists)->toBeTrue();
});

it('asserts a posted comment through the builder, the DTO and the actor trait', function (): void {
    $fake = Comments::fake();
    $post = PostTestModel::create();

    $fake->assertNothingPosted();

    $built = Comments::on($post)->body('Hi')->post();
    Comments::write(new WriteCommentData(commentable: $post, body: 'Via DTO'));
    UserTestModel::create()->writeComment($post, 'Via trait');

    $fake->assertPosted();
    $fake->assertPosted($built);
    $fake->assertPosted(fn (Comment $comment): bool => $comment->comment === 'Via DTO');
    $fake->assertPosted(fn (Comment $comment): bool => $comment->comment === 'Via trait');

    expect(fn () => $fake->assertPosted(fn (Comment $comment): bool => $comment->comment === 'Nope'))
        ->toThrow(AssertionFailedError::class, 'matching the callback')
        ->and(fn () => $fake->assertNothingPosted())
        ->toThrow(AssertionFailedError::class, 'but 3 were');
});

it('fails the posted assertion when nothing was posted', function (): void {
    $fake = Comments::fake();

    expect(fn () => $fake->assertPosted())->toThrow(AssertionFailedError::class, 'but none was')
        ->and(fn () => $fake->assertPosted(Comment::factory()->for(PostTestModel::create(), 'commentable')->create()))
        ->toThrow(AssertionFailedError::class, 'matching the given model');
});

it('records nothing for a call that throws', function (): void {
    $fake = Comments::fake();
    $post = PostTestModel::create();
    Comments::lock($post);

    expect(fn () => Comments::on($post)->body('nope')->post())->toThrow(CommentsLockedException::class);

    $fake->assertNothingPosted();
});

it('asserts each moderation verb, passing and failing', function (string $verb, string $assert, string $nothing): void {
    $fake = Comments::fake();
    $comment = Comments::on(PostTestModel::create())->body('Moderate me')->post();
    $other = Comments::on(PostTestModel::create())->body('Leave me')->post();

    $fake->{$nothing}();
    expect(fn () => $fake->{$assert}())->toThrow(AssertionFailedError::class);

    match ($verb) {
        'update' => Comments::update($comment, 'Edited'),
        'restore' => Comments::restore(tap($comment)->delete()),
        default => Comments::{$verb}($comment),
    };

    $fake->{$assert}();
    $fake->{$assert}($comment);
    $fake->{$assert}(fn (Comment $recorded): bool => $recorded->is($comment));

    expect(fn () => $fake->{$assert}($other))->toThrow(AssertionFailedError::class)
        ->and(fn () => $fake->{$nothing}())->toThrow(AssertionFailedError::class);
})->with([
    'update' => ['update', 'assertUpdated', 'assertNothingUpdated'],
    'delete' => ['delete', 'assertDeleted', 'assertNothingDeleted'],
    'restore' => ['restore', 'assertRestored', 'assertNothingRestored'],
    'approve' => ['approve', 'assertApproved', 'assertNothingApproved'],
    'hide' => ['hide', 'assertHidden', 'assertNothingHidden'],
    'lockThread' => ['lockThread', 'assertThreadLocked', 'assertNothingThreadLocked'],
    'unlockThread' => ['unlockThread', 'assertThreadUnlocked', 'assertNothingThreadUnlocked'],
]);

it('asserts subject locks, passing and failing', function (): void {
    $fake = Comments::fake();
    $post = PostTestModel::create();
    $other = PostTestModel::create();

    $fake->assertNothingLocked();
    $fake->assertNothingUnlocked();

    Comments::lock($post);
    Comments::unlock($post);

    $fake->assertLocked($post);
    $fake->assertUnlocked($post);
    $fake->assertLocked(fn (PostTestModel $subject): bool => $subject->is($post));

    expect(fn () => $fake->assertLocked($other))->toThrow(AssertionFailedError::class)
        ->and(fn () => $fake->assertUnlocked($other))->toThrow(AssertionFailedError::class)
        ->and(fn () => $fake->assertNothingLocked())->toThrow(AssertionFailedError::class)
        ->and(fn () => $fake->assertNothingUnlocked())->toThrow(AssertionFailedError::class);
});

it('records thread locks made through the comment model methods', function (): void {
    $fake = Comments::fake();
    $comment = Comments::on(PostTestModel::create())->body('Thread')->post();
    $other = Comments::on(PostTestModel::create())->body('Other')->post();

    $comment->lockReplies();
    $comment->unlockReplies();

    $fake->assertThreadLocked($comment);
    $fake->assertThreadUnlocked($comment);

    expect(fn () => $fake->assertThreadLocked($other))->toThrow(AssertionFailedError::class);
});

it('answers the subject lock read on the trait through the manager', function (): void {
    $fake = Comments::fake();
    $post = PostTestModel::create();

    expect($post->commentsLocked())->toBeFalse();

    Comments::lock($post);

    expect($post->commentsLocked())->toBeTrue();
    $fake->assertLocked($post);
});

it('records every row of a bulk moderation', function (): void {
    $fake = Comments::fake();
    config()->set('comments.require_approval', true);
    $a = Comments::on(PostTestModel::create())->body('a')->post();
    $b = Comments::on(PostTestModel::create())->body('b')->post();

    Comments::query()->pending()->approveAll();
    Comments::query()->hideAll();
    Comments::query()->deleteAll();

    foreach ([$a, $b] as $comment) {
        $fake->assertApproved($comment);
        $fake->assertHidden($comment);
        $fake->assertDeleted($comment);
    }
});

it('hands constructor-injected code the fake', function (): void {
    $fake = Comments::fake();
    $post = PostTestModel::create();

    app(CommentsManager::class)->on($post)->body('Injected')->post();

    $fake->assertPosted(fn (Comment $comment): bool => $comment->comment === 'Injected');
});
