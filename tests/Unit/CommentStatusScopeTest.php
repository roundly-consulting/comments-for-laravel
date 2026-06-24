<?php

declare(strict_types=1);

use RoundlyConsulting\Comments\Enums\CommentStatus;
use RoundlyConsulting\Comments\Models\Comment;
use RoundlyConsulting\Comments\Tests\ActorTestModel;
use RoundlyConsulting\Comments\Tests\PostTestModel;

function makeComment(CommentStatus $status, bool $visible = true): Comment
{
    $actor = ActorTestModel::create();
    $post = PostTestModel::create();

    return Comment::factory()
        ->for($actor, 'actor')
        ->for($post, 'commentable')
        ->create(['status' => $status, 'visible' => $visible]);
}

it('casts the status column to the enum', function (): void {
    $comment = makeComment(CommentStatus::Pending);

    expect($comment->refresh()->status)->toBe(CommentStatus::Pending);
});

it('returns only approved comments via approved scope', function (): void {
    makeComment(CommentStatus::Approved);
    makeComment(CommentStatus::Pending);
    makeComment(CommentStatus::Hidden);

    expect(Comment::approved()->get())->toHaveCount(1)
        ->and(Comment::approved()->first()->status)->toBe(CommentStatus::Approved);
});

it('returns only pending comments via pending scope', function (): void {
    makeComment(CommentStatus::Approved);
    makeComment(CommentStatus::Pending);

    expect(Comment::pending()->get())->toHaveCount(1)
        ->and(Comment::pending()->first()->status)->toBe(CommentStatus::Pending);
});

it('returns only hidden comments via hidden scope', function (): void {
    makeComment(CommentStatus::Approved);
    makeComment(CommentStatus::Hidden);

    expect(Comment::hidden()->get())->toHaveCount(1)
        ->and(Comment::hidden()->first()->status)->toBe(CommentStatus::Hidden);
});

it('requires both visible and approved for the visible scope', function (): void {
    makeComment(CommentStatus::Approved, visible: true);
    makeComment(CommentStatus::Approved, visible: false);
    makeComment(CommentStatus::Pending, visible: true);

    expect(Comment::visible()->get())->toHaveCount(1);
});

it('returns only top-level comments via roots scope', function (): void {
    $root = makeComment(CommentStatus::Approved);
    Comment::factory()
        ->for(ActorTestModel::create(), 'actor')
        ->for(PostTestModel::create(), 'commentable')
        ->create(['parent_id' => $root->getKey()]);

    expect(Comment::roots()->get())->toHaveCount(1);
});
