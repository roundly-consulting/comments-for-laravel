<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Comments\CommentQuery;
use RoundlyConsulting\Comments\Enums\CommentStatus;
use RoundlyConsulting\Comments\Events\CommentApproved;
use RoundlyConsulting\Comments\Facades\Comments;
use RoundlyConsulting\Comments\Models\Comment;
use RoundlyConsulting\Comments\Tests\ActorTestModel;
use RoundlyConsulting\Comments\Tests\PostTestModel;
use RoundlyConsulting\Reports\Facades\Reports;

it('starts an unscoped query from the facade', function (): void {
    Comments::on(PostTestModel::create())->body('one')->post();
    Comments::on(PostTestModel::create())->body('two')->post();

    expect(Comments::query())->toBeInstanceOf(CommentQuery::class)
        ->and(Comments::query()->count())->toBe(2);
});

it('builds a site-wide moderation queue ordered by reports', function (): void {
    config()->set('comments.require_approval', true);

    $quiet = Comments::on(PostTestModel::create())->body('quiet')->post();
    $noisy = Comments::on(PostTestModel::create())->body('noisy')->post();
    Comment::factory()->for(PostTestModel::create(), 'commentable')->create(['status' => CommentStatus::Approved]);

    Reports::report($noisy)->by(ActorTestModel::create())->create();
    Reports::report($noisy)->by(ActorTestModel::create())->create();
    Reports::report($quiet)->by(ActorTestModel::create())->create();

    $queue = Comments::query()->pending()->mostReported()->paginate();
    $counted = Comments::query()->pending()->withReportCounts()->reportedMoreThan(1)->get();

    expect($queue->total())->toBe(2)
        ->and(collect($queue->items())->map(fn (Comment $comment): string => $comment->comment)->all())
        ->toBe(['noisy', 'quiet'])
        ->and($counted->pluck('comment')->all())->toBe(['noisy'])
        ->and($counted->first()?->getAttribute('reports_count'))->toBe(2);
});

it('narrows a site-wide query to a subject or an author', function (): void {
    $post = PostTestModel::create();
    $author = ActorTestModel::create();

    Comments::on($post)->as($author)->body('mine here')->post();
    Comments::on(PostTestModel::create())->as($author)->body('mine elsewhere')->post();
    Comments::on($post)->body('someone else')->post();

    expect(Comments::query()->for($post)->count())->toBe(2)
        ->and(Comments::query()->byAuthor($author)->count())->toBe(2)
        ->and(Comments::query()->for($post)->byAuthor($author)->count())->toBe(1);
});

it('approves the whole site-wide queue in bulk', function (): void {
    Event::fake([CommentApproved::class]);
    config()->set('comments.require_approval', true);

    Comments::on(PostTestModel::create())->body('a')->post();
    Comments::on(PostTestModel::create())->body('b')->post();

    expect(Comments::query()->pending()->approveAll())->toBe(2)
        ->and(Comments::query()->pending()->count())->toBe(0);

    Event::assertDispatchedTimes(CommentApproved::class, 2);
});
