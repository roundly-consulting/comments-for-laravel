<?php

declare(strict_types=1);

use RoundlyConsulting\Comments\Enums\CommentStatus;
use RoundlyConsulting\Comments\Exceptions\CommentRejectedException;
use RoundlyConsulting\Comments\Facades\Comments;
use RoundlyConsulting\Comments\Models\Comment;
use RoundlyConsulting\Comments\Tests\PostTestModel;

beforeEach(function (): void {
    config()->set('comments.blocklist', ['spam', '/badword/i']);
});

it('rejects a blocklisted comment by default', function (): void {
    $post = PostTestModel::create();

    Comments::on($post)->body('this is spam')->post();
})->throws(CommentRejectedException::class);

it('matches whole words case-insensitively', function (): void {
    $post = PostTestModel::create();

    Comments::on($post)->body('SPAM everywhere')->post();
})->throws(CommentRejectedException::class);

it('does not match a banned word inside another word', function (): void {
    config()->set('comments.blocklist', ['cat']);
    $post = PostTestModel::create();

    $comment = Comments::on($post)->body('I love education')->post();

    expect($comment->status)->toBe(CommentStatus::Approved);
});

it('matches a configured regex', function (): void {
    $post = PostTestModel::create();

    Comments::on($post)->body('contains a BADWORD here')->post();
})->throws(CommentRejectedException::class);

it('hides instead of rejecting when configured', function (): void {
    config()->set('comments.blocklist_action', 'hidden');
    $post = PostTestModel::create();

    $comment = Comments::on($post)->body('this is spam')->post();

    expect($comment->status)->toBe(CommentStatus::Hidden);
});

it('holds for review when configured to pending', function (): void {
    config()->set('comments.blocklist_action', 'pending');
    $post = PostTestModel::create();

    $comment = Comments::on($post)->body('this is spam')->post();

    expect($comment->status)->toBe(CommentStatus::Pending);
});

it('rejects a blocklisted edit', function (): void {
    config()->set('comments.blocklist', []);
    $post = PostTestModel::create();
    $comment = Comments::on($post)->body('clean')->post();

    config()->set('comments.blocklist', ['spam']);

    Comments::update($comment, 'now spam');
})->throws(CommentRejectedException::class);

it('holds a blocklisted edit for review when configured to pending', function (): void {
    config()->set('comments.blocklist', ['casino']);
    config()->set('comments.blocklist_action', 'pending');
    $post = PostTestModel::create();
    $comment = Comments::on($post)->body('hello')->post();

    Comments::update($comment, 'visit casino now');

    expect($comment->fresh()->status)->toBe(CommentStatus::Pending)
        ->and(Comment::visible()->count())->toBe(0);
});

it('hides a blocklisted edit when configured to hidden', function (): void {
    config()->set('comments.blocklist', ['casino']);
    config()->set('comments.blocklist_action', 'hidden');
    $post = PostTestModel::create();
    $comment = Comments::on($post)->body('hello')->post();

    Comments::update($comment, 'visit casino now');

    expect($comment->fresh()->status)->toBe(CommentStatus::Hidden);
});

it('never lifts a hidden comment to pending on a blocklisted edit', function (): void {
    config()->set('comments.blocklist', ['casino']);
    config()->set('comments.blocklist_action', 'pending');
    $post = PostTestModel::create();
    $comment = Comment::factory()->for($post, 'commentable')->hidden()->create();

    Comments::update($comment, 'visit casino now');

    expect($comment->fresh()->status)->toBe(CommentStatus::Hidden);
});

it('leaves the status alone on a clean edit', function (): void {
    config()->set('comments.blocklist', ['casino']);
    config()->set('comments.blocklist_action', 'pending');
    $post = PostTestModel::create();
    $comment = Comment::factory()->for($post, 'commentable')->pending()->create();

    Comments::update($comment, 'perfectly clean');

    expect($comment->fresh()->status)->toBe(CommentStatus::Pending);
});

it('lets clean comments through untouched', function (): void {
    $post = PostTestModel::create();

    $comment = Comments::on($post)->body('a perfectly fine comment')->post();

    expect($comment->status)->toBe(CommentStatus::Approved);
});
