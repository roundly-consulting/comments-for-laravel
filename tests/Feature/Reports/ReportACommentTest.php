<?php

declare(strict_types=1);

use RoundlyConsulting\Comments\Facades\Comments;
use RoundlyConsulting\Comments\Models\Comment;
use RoundlyConsulting\Comments\Tests\ActorTestModel;
use RoundlyConsulting\Comments\Tests\PostTestModel;
use RoundlyConsulting\Reports\Contracts\Reportable;
use RoundlyConsulting\Reports\Exceptions\DuplicateReportException;
use RoundlyConsulting\Reports\Facades\Reports;

function reportableComment(): Comment
{
    return Comment::factory()->for(PostTestModel::create(), 'commentable')->create();
}

it('is a reportable subject', function (): void {
    expect(reportableComment())->toBeInstanceOf(Reportable::class);
});

it('reports a comment with a typed reason', function (): void {
    $comment = reportableComment();
    $reporter = ActorTestModel::create();

    Reports::report($comment)->by($reporter)->for('spam')->create();

    expect($comment->hasBeenReported())->toBeTrue()
        ->and($comment->isReportedBy($reporter))->toBeTrue()
        ->and($comment->reportsCount())->toBe(1);
});

it('rejects a duplicate report by the same reporter', function (): void {
    $comment = reportableComment();
    $reporter = ActorTestModel::create();

    Reports::report($comment)->by($reporter)->for('spam')->create();
    Reports::report($comment)->by($reporter)->for('abuse')->create();
})->throws(DuplicateReportException::class);

it('accepts a guest report', function (): void {
    $comment = reportableComment();

    Reports::report($comment)->asGuest('guest-1')->for('abuse')->create();

    expect($comment->hasBeenReported())->toBeTrue();
});

it('surfaces a moderation queue ordered by report volume', function (): void {
    $post = PostTestModel::create();
    $noisy = Comment::factory()->for($post, 'commentable')->create();
    $quiet = Comment::factory()->for($post, 'commentable')->create();

    Reports::report($noisy)->by(ActorTestModel::create())->for('spam')->create();
    Reports::report($noisy)->by(ActorTestModel::create())->for('spam')->create();
    Reports::report($quiet)->by(ActorTestModel::create())->for('spam')->create();

    $queue = Comments::for($post)->mostReported()->get();

    expect($queue->first()->is($noisy))->toBeTrue()
        ->and((int) $queue->first()->reports_count)->toBe(2);

    $overThreshold = Comments::for($post)->reportedMoreThan(1)->get();

    expect($overThreshold)->toHaveCount(1)
        ->and($overThreshold->first()->is($noisy))->toBeTrue();
});
