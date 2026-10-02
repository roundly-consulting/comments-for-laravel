<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use RoundlyConsulting\Comments\Events\CommentThreadLocked;
use RoundlyConsulting\Comments\Events\CommentThreadUnlocked;
use RoundlyConsulting\Comments\Exceptions\CommentsLockedException;
use RoundlyConsulting\Comments\Exceptions\UnauthorizedCommentActionException;
use RoundlyConsulting\Comments\Facades\Comments;
use RoundlyConsulting\Comments\Models\Comment;
use RoundlyConsulting\Comments\Tests\DenyingCommentPolicy;
use RoundlyConsulting\Comments\Tests\PostTestModel;
use RoundlyConsulting\Comments\Tests\UserTestModel;

/**
 * @return array{0: PostTestModel, 1: Comment, 2: Comment}
 */
function threadWithReply(): array
{
    $post = PostTestModel::create();
    $root = Comments::on($post)->body('root')->post();
    $child = Comments::on($post)->reply($root)->body('child')->post();

    return [$post, $root, $child];
}

it('locks and unlocks a thread through the facade', function (): void {
    Event::fake([CommentThreadLocked::class, CommentThreadUnlocked::class]);
    [, $root] = threadWithReply();

    expect(Comments::lockThread($root)->isLocked())->toBeTrue()
        ->and(Comments::isThreadLocked($root))->toBeTrue();

    Event::assertDispatched(CommentThreadLocked::class, fn (CommentThreadLocked $event): bool => $event->comment->is($root));

    expect(Comments::unlockThread($root)->isLocked())->toBeFalse()
        ->and(Comments::isThreadLocked($root))->toBeFalse();

    Event::assertDispatched(CommentThreadUnlocked::class, fn (CommentThreadUnlocked $event): bool => $event->comment->is($root));
});

it('fires nothing when the thread is already in the requested state', function (): void {
    Event::fake([CommentThreadLocked::class, CommentThreadUnlocked::class]);
    [, $root] = threadWithReply();

    Comments::unlockThread($root);
    Comments::lockThread($root);
    $lockedAt = $root->fresh()?->locked_at;
    $this->travel(5)->minutes();
    Comments::lockThread($root);

    expect($root->fresh()?->locked_at?->equalTo($lockedAt))->toBeTrue();

    Event::assertDispatchedTimes(CommentThreadLocked::class, 1);
    Event::assertNotDispatched(CommentThreadUnlocked::class);
});

it('blocks a reply to a reply inside a locked thread', function (): void {
    [$post, $root, $child] = threadWithReply();
    Comments::lockThread($root);

    Comments::on($post)->reply($child->fresh())->body('grandchild')->post();
})->throws(CommentsLockedException::class);

it('blocks editing a reply inside a locked thread', function (): void {
    [, $root, $child] = threadWithReply();
    Comments::lockThread($root);

    Comments::update($child->fresh(), 'edited');
})->throws(CommentsLockedException::class);

it('reports a comment under a locked ancestor as thread-locked', function (): void {
    [, $root, $child] = threadWithReply();
    Comments::lockThread($root);

    expect($child->fresh()?->isLocked())->toBeFalse()
        ->and(Comments::isThreadLocked($child->fresh()))->toBeTrue();
});

it('keeps a thread open outside the locked branch', function (): void {
    [$post, $root, $child] = threadWithReply();
    $sibling = Comments::on($post)->reply($root)->body('sibling')->post();
    Comments::lockThread($child);

    $reply = Comments::on($post)->reply($sibling)->body('fine')->post();

    expect($reply->exists)->toBeTrue()
        ->and(Comments::isThreadLocked($root))->toBeFalse()
        ->and(Comments::isThreadLocked($sibling))->toBeFalse();
});

it('stops the ancestor walk at a missing parent', function (): void {
    [, $root, $child] = threadWithReply();
    $root->delete();

    expect(Comments::isThreadLocked($child->fresh()))->toBeFalse();
});

it('stops the ancestor walk at a parent cycle', function (): void {
    $comment = Comments::on(PostTestModel::create())->body('loop')->post();
    $comment->forceFill(['parent_id' => $comment->getKey()])->saveQuietly();

    expect(Comments::isThreadLocked($comment->fresh()))->toBeFalse();
});

it('locks a thread through the model methods', function (): void {
    [, $root] = threadWithReply();

    expect($root->lockReplies()->isLocked())->toBeTrue()
        ->and($root->unlockReplies()->isLocked())->toBeFalse();
});

it('blocks thread locking through the facade when the policy denies it', function (): void {
    [, $locked] = threadWithReply();
    Comments::lockThread($locked);
    [, $open] = threadWithReply();

    config()->set('comments.authorization', true);
    $this->actingAs(UserTestModel::create());
    Gate::policy(Comment::class, DenyingCommentPolicy::class);

    expect(fn () => Comments::lockThread($open))->toThrow(UnauthorizedCommentActionException::class)
        ->and($open->fresh()?->isLocked())->toBeFalse()
        ->and(fn () => Comments::unlockThread($locked))->toThrow(UnauthorizedCommentActionException::class)
        ->and($locked->fresh()?->isLocked())->toBeTrue();
});

it('keeps a thread locked below a soft-deleted comment', function (): void {
    config()->set('comments.max_depth', 10);
    $post = PostTestModel::create();
    $a = Comments::on($post)->body('A')->post();
    $b = Comments::on($post)->reply($a)->body('B')->post();
    $c = Comments::on($post)->reply($b)->body('C')->post();
    Comments::lockThread($a);

    Comments::delete($b);
    $c = Comment::query()->findOrFail($c->getKey());

    expect(Comments::isThreadLocked($c))->toBeTrue()
        ->and(fn () => Comments::on($post)->reply($c)->body('D')->post())->toThrow(CommentsLockedException::class)
        ->and(fn () => Comments::update($c, 'edited'))->toThrow(CommentsLockedException::class);
});
