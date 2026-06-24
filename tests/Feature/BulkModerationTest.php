<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Comments\Enums\CommentStatus;
use RoundlyConsulting\Comments\Events\CommentApproved;
use RoundlyConsulting\Comments\Events\CommentDeleted;
use RoundlyConsulting\Comments\Events\CommentHidden;
use RoundlyConsulting\Comments\Facades\Comments;
use RoundlyConsulting\Comments\Models\Comment;
use RoundlyConsulting\Comments\Tests\ActorTestModel;
use RoundlyConsulting\Comments\Tests\PostTestModel;

it('approves every pending comment for a subject', function (): void {
    Event::fake([CommentApproved::class]);
    $post = PostTestModel::create();
    Comment::factory()->for($post, 'commentable')->pending()->count(3)->create();

    $affected = Comments::for($post)->pending()->approveAll();

    expect($affected)->toBe(3)
        ->and(Comments::for($post)->approved()->count())->toBe(3);
    Event::assertDispatchedTimes(CommentApproved::class, 3);
});

it('hides every comment by an author', function (): void {
    Event::fake([CommentHidden::class]);
    $post = PostTestModel::create();
    $user = ActorTestModel::create();
    Comment::factory()->for($post, 'commentable')->by($user)->count(2)->create();

    $affected = Comments::byAuthor($user)->hideAll();

    expect($affected)->toBe(2)
        ->and(Comment::query()->where('status', CommentStatus::Hidden)->count())->toBe(2);
    Event::assertDispatchedTimes(CommentHidden::class, 2);
});

it('deletes every comment for a subject', function (): void {
    Event::fake([CommentDeleted::class]);
    $post = PostTestModel::create();
    Comment::factory()->for($post, 'commentable')->count(2)->create();

    $affected = Comments::for($post)->deleteAll();

    expect($affected)->toBe(2)
        ->and(Comments::for($post)->count())->toBe(0);
    Event::assertDispatchedTimes(CommentDeleted::class, 2);
});
