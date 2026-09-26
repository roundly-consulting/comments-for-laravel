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
use RoundlyConsulting\Reports\Facades\Reports;

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

it('approves every match even when the matches outnumber one chunk', function (): void {
    // Regression: the bulk helpers walked the query with offset pages (`each()` → chunk of
    // 1000). Approving a page shrinks a `pending()` result set, so the next offset skipped a
    // page's worth of comments and they stayed pending.
    $post = PostTestModel::create();
    $now = now();

    foreach (array_chunk(range(1, 1005), 250) as $chunk) {
        Comment::query()->insert(array_map(fn (int $i): array => [
            'visible' => true,
            'status' => CommentStatus::Pending->value,
            'commentable_type' => $post->getMorphClass(),
            'commentable_id' => $post->getKey(),
            'comment' => "comment {$i}",
            'created_at' => $now,
            'updated_at' => $now,
        ], $chunk));
    }

    $affected = Comments::for($post)->pending()->newest()->approveAll();

    expect($affected)->toBe(1005)
        ->and(Comments::for($post)->pending()->count())->toBe(0);
});

it('deletes every match even when the matches outnumber one chunk', function (): void {
    $post = PostTestModel::create();
    $now = now();

    foreach (array_chunk(range(1, 1005), 250) as $chunk) {
        Comment::query()->insert(array_map(fn (int $i): array => [
            'visible' => true,
            'status' => CommentStatus::Approved->value,
            'commentable_type' => $post->getMorphClass(),
            'commentable_id' => $post->getKey(),
            'comment' => "comment {$i}",
            'created_at' => $now,
            'updated_at' => $now,
        ], $chunk));
    }

    expect(Comments::for($post)->deleteAll())->toBe(1005)
        ->and(Comments::for($post)->count())->toBe(0);
});

it('bulk-hides a ranked moderation queue', function (): void {
    // The key cursor drops the queue's own ordering (report volume) and keeps its filter.
    $post = PostTestModel::create();
    [$loud, $quiet, $clean] = Comment::factory()->for($post, 'commentable')->count(3)->create()->all();

    Reports::report($loud)->by(ActorTestModel::create())->for('spam')->create();
    Reports::report($loud)->by(ActorTestModel::create())->for('spam')->create();
    Reports::report($quiet)->by(ActorTestModel::create())->for('spam')->create();

    expect(Comments::for($post)->mostReported()->reportedMoreThan(0)->hideAll())->toBe(2)
        ->and($loud->fresh()?->status)->toBe(CommentStatus::Hidden)
        ->and($quiet->fresh()?->status)->toBe(CommentStatus::Hidden)
        ->and($clean->fresh()?->status)->toBe(CommentStatus::Approved);
});
