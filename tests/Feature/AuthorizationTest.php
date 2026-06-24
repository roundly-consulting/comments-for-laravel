<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use RoundlyConsulting\Comments\Exceptions\UnauthorizedCommentActionException;
use RoundlyConsulting\Comments\Facades\Comments;
use RoundlyConsulting\Comments\Models\Comment;
use RoundlyConsulting\Comments\Policies\CommentPolicy;
use RoundlyConsulting\Comments\Tests\DenyingCommentPolicy;
use RoundlyConsulting\Comments\Tests\PostTestModel;
use RoundlyConsulting\Comments\Tests\UserTestModel;

it('permits everything when authorization is disabled', function (): void {
    config()->set('comments.authorization', false);
    $this->actingAs(UserTestModel::create());
    Gate::policy(Comment::class, DenyingCommentPolicy::class);

    $post = PostTestModel::create();
    $comment = Comments::on($post)->body('allowed')->post();

    expect($comment)->toBeInstanceOf(Comment::class);
});

it('blocks writing when the create ability denies', function (): void {
    config()->set('comments.authorization', true);
    $this->actingAs(UserTestModel::create());
    Gate::define('create', fn ($user): bool => false);

    $post = PostTestModel::create();

    Comments::on($post)->body('nope')->post();
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
        ->and($policy->moderate(null, $comment))->toBeTrue();
});
