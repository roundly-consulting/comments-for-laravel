<?php

declare(strict_types=1);

use RoundlyConsulting\Comments\Exceptions\CommentsLockedException;
use RoundlyConsulting\Comments\Facades\Comments;
use RoundlyConsulting\Comments\Models\Comment;
use RoundlyConsulting\Comments\Tests\PostTestModel;

it('locks and unlocks a subject', function (): void {
    $post = PostTestModel::create();

    Comments::lock($post);
    expect($post->commentsLocked())->toBeTrue()
        ->and(Comments::isLocked($post))->toBeTrue();

    Comments::unlock($post);
    expect($post->commentsLocked())->toBeFalse();
});

it('blocks new comments on a locked subject', function (): void {
    $post = PostTestModel::create();
    Comments::lock($post);

    Comments::on($post)->body('nope')->post();
})->throws(CommentsLockedException::class);

it('blocks edits on a locked subject', function (): void {
    $post = PostTestModel::create();
    $comment = Comments::on($post)->body('first')->post();
    Comments::lock($post);

    Comments::update($comment, 'edited');
})->throws(CommentsLockedException::class);

it('is idempotent when locking twice', function (): void {
    $post = PostTestModel::create();

    Comments::lock($post);
    Comments::lock($post);

    expect($post->commentsLocked())->toBeTrue();
});

it('locks a single reply chain via the comment', function (): void {
    $post = PostTestModel::create();
    $root = Comments::on($post)->body('root')->post();

    $root->lockReplies();
    expect($root->isLocked())->toBeTrue();

    Comments::on($post)->reply($root->fresh())->body('reply')->post();
})->throws(CommentsLockedException::class);

it('blocks editing a comment whose own reply chain is locked', function (): void {
    $post = PostTestModel::create();
    $comment = Comments::on($post)->body('first')->post();
    $comment->lockReplies();

    Comments::update($comment->fresh(), 'edited');
})->throws(CommentsLockedException::class);

it('unlocks a reply chain', function (): void {
    $post = PostTestModel::create();
    $root = Comments::on($post)->body('root')->post();
    $root->lockReplies();

    $root->unlockReplies();

    $reply = Comments::on($post)->reply($root->fresh())->body('reply')->post();
    expect($reply)->toBeInstanceOf(Comment::class);
});
