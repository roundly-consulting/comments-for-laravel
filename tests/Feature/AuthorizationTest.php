<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use RoundlyConsulting\Comments\Exceptions\UnauthorizedCommentActionException;
use RoundlyConsulting\Comments\Facades\Comments;
use RoundlyConsulting\Comments\Models\Comment;
use RoundlyConsulting\Comments\Policies\CommentPolicy;
use RoundlyConsulting\Comments\Tests\DenyingCommentPolicy;
use RoundlyConsulting\Comments\Tests\PostTestModel;
use RoundlyConsulting\Comments\Tests\SubjectScopedCommentPolicy;
use RoundlyConsulting\Comments\Tests\UserTestModel;

it('permits everything when authorization is disabled', function (): void {
    config()->set('comments.authorization', false);
    $this->actingAs(UserTestModel::create());
    Gate::policy(Comment::class, DenyingCommentPolicy::class);

    $post = PostTestModel::create();
    $comment = Comments::on($post)->body('allowed')->post();

    expect($comment)->toBeInstanceOf(Comment::class);
});

it('blocks writing when the policy denies create', function (): void {
    config()->set('comments.authorization', true);
    $this->actingAs(UserTestModel::create());
    Gate::policy(Comment::class, DenyingCommentPolicy::class);

    $post = PostTestModel::create();

    Comments::on($post)->body('nope')->post();
})->throws(UnauthorizedCommentActionException::class);

it('lets a granting policy allow writing', function (): void {
    // Regression: the write flow asked the Gate for a bare `create` ability with no model, so
    // the Comment policy was never consulted and every write was denied once authorization
    // was on — even with the shipped permissive policy registered.
    config()->set('comments.authorization', true);
    $this->actingAs(UserTestModel::create());
    Gate::policy(Comment::class, CommentPolicy::class);

    $comment = Comments::on(PostTestModel::create())->body('allowed')->post();

    expect($comment->exists)->toBeTrue();
});

it('lets a granting policy allow a guest to write', function (): void {
    config()->set('comments.authorization', true);
    Gate::policy(Comment::class, CommentPolicy::class);

    $comment = Comments::on(PostTestModel::create())->body('guest')->post();

    expect($comment->exists)->toBeTrue();
});

it('auto-discovers the shipped policy when none is registered', function (): void {
    config()->set('comments.authorization', true);
    $this->actingAs(UserTestModel::create());

    expect(Gate::getPolicyFor(Comment::class))->toBeInstanceOf(CommentPolicy::class)
        ->and(Comments::on(PostTestModel::create())->body('allowed')->post()->exists)->toBeTrue();
});

it('ignores a global create ability that is not the comment policy', function (): void {
    // A host's unrelated `create` gate must not decide who may comment: the check is the
    // Comment policy's `create` ability, resolved from the Comment model.
    config()->set('comments.authorization', true);
    $this->actingAs(UserTestModel::create());
    Gate::define('create', fn ($user): bool => true);
    Gate::policy(Comment::class, DenyingCommentPolicy::class);

    Comments::on(PostTestModel::create())->body('nope')->post();
})->throws(UnauthorizedCommentActionException::class);

it('hands the subject being commented on to the create policy', function (): void {
    config()->set('comments.authorization', true);
    $this->actingAs(UserTestModel::create());
    Gate::policy(Comment::class, SubjectScopedCommentPolicy::class);

    $open = PostTestModel::create();
    $closed = PostTestModel::create();
    SubjectScopedCommentPolicy::$openSubjectKey = $open->getKey();

    expect(Comments::on($open)->body('fine')->post()->exists)->toBeTrue()
        ->and(fn () => Comments::on($closed)->body('nope')->post())
        ->toThrow(UnauthorizedCommentActionException::class);
});

it('authorizes a reply against the root subject', function (): void {
    $open = PostTestModel::create();
    $root = Comments::on($open)->body('root')->post();

    config()->set('comments.authorization', true);
    $this->actingAs(UserTestModel::create());
    Gate::policy(Comment::class, SubjectScopedCommentPolicy::class);
    SubjectScopedCommentPolicy::$openSubjectKey = $open->getKey();

    // The builder's subject is ignored for a reply (it inherits the parent's), so the
    // policy must see the parent's subject too — not whatever the caller passed.
    $reply = Comments::on(PostTestModel::create())->reply($root)->body('reply')->post();

    expect($reply->commentable_id)->toBe($open->getKey());
});

it('writes through the actor trait under the policy', function (): void {
    config()->set('comments.authorization', true);
    $user = UserTestModel::create();
    $this->actingAs($user);
    Gate::policy(Comment::class, DenyingCommentPolicy::class);

    $user->writeComment(PostTestModel::create(), 'nope');
})->throws(UnauthorizedCommentActionException::class);

it('blocks editing when the policy denies update', function (): void {
    $post = PostTestModel::create();
    $comment = Comments::on($post)->body('first')->post();

    config()->set('comments.authorization', true);
    $this->actingAs(UserTestModel::create());
    Gate::policy(Comment::class, DenyingCommentPolicy::class);

    Comments::update($comment, 'edited');
})->throws(UnauthorizedCommentActionException::class);

it('blocks moderation when the policy denies moderate', function (): void {
    $post = PostTestModel::create();
    $comment = Comments::on($post)->body('first')->post();

    config()->set('comments.authorization', true);
    $this->actingAs(UserTestModel::create());
    Gate::policy(Comment::class, DenyingCommentPolicy::class);

    Comments::hide($comment);
})->throws(UnauthorizedCommentActionException::class);

it('blocks deletion when the policy denies delete', function (): void {
    $post = PostTestModel::create();
    $comment = Comments::on($post)->body('first')->post();

    config()->set('comments.authorization', true);
    $this->actingAs(UserTestModel::create());
    Gate::policy(Comment::class, DenyingCommentPolicy::class);

    Comments::delete($comment);
})->throws(UnauthorizedCommentActionException::class);

it('allows actions when the policy grants', function (): void {
    $post = PostTestModel::create();
    $comment = Comments::on($post)->body('first')->post();

    config()->set('comments.authorization', true);
    $this->actingAs(UserTestModel::create());
    Gate::policy(Comment::class, CommentPolicy::class);

    expect(Comments::hide($comment)->status->value)->toBe('hidden');
});

it('the shipped policy is permissive by default', function (): void {
    $policy = new CommentPolicy;
    $comment = Comment::factory()->for(PostTestModel::create(), 'commentable')->create();

    expect($policy->create(null))->toBeTrue()
        ->and($policy->update(null, $comment))->toBeTrue()
        ->and($policy->delete(null, $comment))->toBeTrue()
        ->and($policy->moderate(null, $comment))->toBeTrue()
        ->and($policy->restore(null, $comment))->toBeTrue()
        ->and($policy->lock(null, $comment))->toBeTrue()
        ->and($policy->unlock(null, $comment))->toBeTrue();
});

/*
 * restore / lock / unlock are gated too (they were the only mutations that ignored
 * `comments.authorization`): `restore` gets the comment, `lock` / `unlock` get what is
 * being locked — the subject, or the comment itself for a reply-chain lock.
 */

it('blocks restoring when the policy denies restore', function (): void {
    $comment = Comments::on(PostTestModel::create())->body('first')->post();
    Comments::delete($comment);

    config()->set('comments.authorization', true);
    $this->actingAs(UserTestModel::create());
    Gate::policy(Comment::class, DenyingCommentPolicy::class);

    expect(fn () => Comments::restore($comment))->toThrow(UnauthorizedCommentActionException::class)
        ->and($comment->fresh()?->trashed())->toBeTrue();
});

it('blocks locking and unlocking a subject when the policy denies them', function (): void {
    $locked = PostTestModel::create();
    $open = PostTestModel::create();
    Comments::lock($locked);

    config()->set('comments.authorization', true);
    $this->actingAs(UserTestModel::create());
    Gate::policy(Comment::class, DenyingCommentPolicy::class);

    expect(fn () => Comments::lock($open))->toThrow(UnauthorizedCommentActionException::class)
        ->and(Comments::isLocked($open))->toBeFalse()
        ->and(fn () => Comments::unlock($locked))->toThrow(UnauthorizedCommentActionException::class)
        ->and(Comments::isLocked($locked))->toBeTrue();
});

it('blocks locking and unlocking a reply chain when the policy denies them', function (): void {
    $locked = Comments::on(PostTestModel::create())->body('locked')->post()->lockReplies();
    $open = Comments::on(PostTestModel::create())->body('open')->post();

    config()->set('comments.authorization', true);
    $this->actingAs(UserTestModel::create());
    Gate::policy(Comment::class, DenyingCommentPolicy::class);

    expect(fn () => $open->lockReplies())->toThrow(UnauthorizedCommentActionException::class)
        ->and($open->fresh()?->isLocked())->toBeFalse()
        ->and(fn () => $locked->unlockReplies())->toThrow(UnauthorizedCommentActionException::class)
        ->and($locked->fresh()?->isLocked())->toBeTrue();
});

it('hands what is being locked to the lock and unlock policy', function (): void {
    config()->set('comments.authorization', true);
    $this->actingAs(UserTestModel::create());
    Gate::policy(Comment::class, SubjectScopedCommentPolicy::class);

    $mine = PostTestModel::create();
    $theirs = PostTestModel::create();
    SubjectScopedCommentPolicy::$openSubjectKey = $mine->getKey();

    Comments::lock($mine);

    expect(Comments::isLocked($mine))->toBeTrue()
        ->and(fn () => Comments::lock($theirs))->toThrow(UnauthorizedCommentActionException::class);

    Comments::unlock($mine);

    expect(Comments::isLocked($mine))->toBeFalse();
});

it('lets the shipped policy restore, lock and unlock', function (): void {
    $post = PostTestModel::create();
    $comment = Comments::on($post)->body('first')->post();
    Comments::delete($comment);

    config()->set('comments.authorization', true);
    $this->actingAs(UserTestModel::create());

    Comments::restore($comment);
    Comments::lock($post);
    $locked = Comments::isLocked($post);
    Comments::unlock($post);
    $comment->lockReplies()->unlockReplies();

    expect($comment->fresh()?->trashed())->toBeFalse()
        ->and($locked)->toBeTrue()
        ->and(Comments::isLocked($post))->toBeFalse()
        ->and($comment->fresh()?->isLocked())->toBeFalse();
});
