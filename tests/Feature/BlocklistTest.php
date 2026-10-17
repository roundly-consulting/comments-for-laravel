<?php

declare(strict_types=1);

use RoundlyConsulting\Comments\CommentsManager;
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

/**
 * C-6 — `Comments::blocklisted()`: the write path's filter, as a read.
 */
it('answers whether a body is blocklisted', function (): void {
    expect(Comments::blocklisted('this is spam'))->toBeTrue()
        ->and(Comments::blocklisted('SPAM everywhere'))->toBeTrue()
        ->and(Comments::blocklisted('contains a BADWORD here'))->toBeTrue()
        ->and(Comments::blocklisted('a perfectly fine comment'))->toBeFalse()
        ->and(Comments::blocklisted('spammer'))->toBeFalse();
});

it('answers without throwing whatever the blocklist action', function (string $action): void {
    config()->set('comments.blocklist_action', $action);

    expect(Comments::blocklisted('this is spam'))->toBeTrue();
})->with(['reject', 'pending', 'hidden']);

it('answers false with no blocklist configured', function (): void {
    config()->set('comments.blocklist', []);

    expect(Comments::blocklisted('this is spam'))->toBeFalse();
});

it('checks the body exactly as posting does — trimmed', function (): void {
    config()->set('comments.blocklist', ['/^spam/']);
    config()->set('comments.blocklist_action', 'pending');

    $comment = Comments::on(PostTestModel::create())->body('   spam first')->post();

    expect($comment->status)->toBe(CommentStatus::Pending)
        ->and(Comments::blocklisted('   spam first'))->toBeTrue();
});

it('tells a blocklist hold apart from an approval hold', function (): void {
    config()->set('comments.require_approval', true);
    config()->set('comments.blocklist_action', 'pending');
    $post = PostTestModel::create();

    $blocked = Comments::on($post)->body('this is spam')->post();
    $waiting = Comments::on($post)->body('a fine comment')->post();

    expect($blocked->status)->toBe(CommentStatus::Pending)
        ->and($waiting->status)->toBe(CommentStatus::Pending)
        ->and(Comments::blocklisted($blocked->comment))->toBeTrue()
        ->and(Comments::blocklisted($waiting->comment))->toBeFalse();
});

it('answers through an injected manager', function (): void {
    expect(app(CommentsManager::class)->blocklisted('this is spam'))->toBeTrue();
});
