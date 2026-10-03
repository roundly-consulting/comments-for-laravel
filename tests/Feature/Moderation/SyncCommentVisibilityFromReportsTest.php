<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Approvals\Enums\ApprovalRule;
use RoundlyConsulting\Comments\Enums\CommentStatus;
use RoundlyConsulting\Comments\Events\CommentHidden;
use RoundlyConsulting\Comments\Listeners\SyncCommentVisibilityFromReports;
use RoundlyConsulting\Comments\Models\Comment;
use RoundlyConsulting\Comments\Tests\ActorTestModel;
use RoundlyConsulting\Comments\Tests\ModeratorTestModel;
use RoundlyConsulting\Comments\Tests\PostTestModel;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;
use RoundlyConsulting\Reports\Enums\Status;
use RoundlyConsulting\Reports\Events\ReportResolved;
use RoundlyConsulting\Reports\Exceptions\ModeratorRequiredException;
use RoundlyConsulting\Reports\Facades\Reports;
use RoundlyConsulting\Reports\Models\Report;

function approvedComment(): Comment
{
    return Comment::factory()->for(PostTestModel::create(), 'commentable')->create();
}

function reportAndUphold(Comment $comment): void
{
    $report = Reports::report($comment)
        ->by(ActorTestModel::create())
        ->for('spam')
        ->create();

    Reports::resolve($report);
}

it('hides an approved comment when a report is upheld', function (): void {
    Event::fake([CommentHidden::class]);

    $comment = approvedComment();

    reportAndUphold($comment);

    expect($comment->fresh()->status)->toBe(CommentStatus::Hidden);
    Event::assertDispatched(CommentHidden::class);
});

it('leaves the comment approved when the resolved sync is disabled', function (): void {
    config()->set('comments.moderation.on_resolved', null);

    $comment = approvedComment();

    reportAndUphold($comment);

    expect($comment->fresh()->status)->toBe(CommentStatus::Approved);
});

it('refuses an unrecognised action instead of switching auto-hide off (strict config)', function (): void {
    config()->set('comments.moderation.on_resolved', 'hdie');

    $comment = approvedComment();

    expect(fn () => reportAndUphold($comment))->toThrow(
        InvalidConfigurationException::class,
        'Configuration value [comments.moderation.on_resolved] must be one of [hide], [hdie] given.',
    );
});

it('ignores a non-comment subject', function (): void {
    $subject = ActorTestModel::create();

    $report = Reports::report($subject)
        ->by(ActorTestModel::create())
        ->for('spam')
        ->create();

    Reports::resolve($report);

    expect($report->fresh()->status)->toBe(Status::Resolved)
        ->and($subject->exists)->toBeTrue();
});

it('is idempotent for an already-hidden comment', function (): void {
    Event::fake([CommentHidden::class]);

    $comment = Comment::factory()->hidden()->for(PostTestModel::create(), 'commentable')->create();

    reportAndUphold($comment);

    expect($comment->fresh()->status)->toBe(CommentStatus::Hidden);
    Event::assertNotDispatched(CommentHidden::class);
});

it('hides a comment when it crosses the report threshold', function (): void {
    config()->set('reports.threshold', 2);

    $comment = approvedComment();

    Reports::report($comment)->by(ActorTestModel::create())->for('spam')->create();
    Reports::report($comment)->by(ActorTestModel::create())->for('spam')->create();

    expect($comment->fresh()->status)->toBe(CommentStatus::Hidden);
});

it('leaves the comment approved on threshold when auto-hide is off', function (): void {
    config()->set('reports.threshold', 2);
    config()->set('comments.moderation.auto_hide', false);

    $comment = approvedComment();

    Reports::report($comment)->by(ActorTestModel::create())->for('spam')->create();
    Reports::report($comment)->by(ActorTestModel::create())->for('spam')->create();

    expect($comment->fresh()->status)->toBe(CommentStatus::Approved);
});

it('hides the comment through a multi-moderator sign-off', function (): void {
    $comment = approvedComment();

    $report = Reports::report($comment)
        ->by(ActorTestModel::create())
        ->for('spam')
        ->create();

    $alice = ModeratorTestModel::create();
    $bob = ModeratorTestModel::create();

    Reports::moderate($report)
        ->requiring([$alice, $bob])
        ->rule(ApprovalRule::Quorum)
        ->quorum(2)
        ->open();

    Reports::resolve($report, by: $alice);

    // One of two — quorum not yet met, comment stays approved.
    expect($comment->fresh()->status)->toBe(CommentStatus::Approved);

    Reports::resolve($report, by: $bob, note: 'Spam');

    // Quorum reached → ReportResolved → comment hidden.
    expect($comment->fresh()->status)->toBe(CommentStatus::Hidden);
});

it('refuses an outsider deciding a multi-moderator sign-off and keeps the comment visible', function (): void {
    $comment = approvedComment();

    $report = Reports::report($comment)
        ->by(ActorTestModel::create())
        ->for('spam')
        ->create();

    $alice = ModeratorTestModel::create();
    $bob = ModeratorTestModel::create();
    $mallory = ModeratorTestModel::create();

    Reports::moderate($report)
        ->requiring([$alice, $bob])
        ->rule(ApprovalRule::Any)
        ->open();

    expect(fn () => Reports::resolve($report, by: $mallory))
        ->toThrow(ModeratorRequiredException::class);

    expect($comment->fresh()->status)->toBe(CommentStatus::Approved)
        ->and($report->fresh()->status)->not->toBe(Status::Resolved);
});

it('ignores a report whose subject is missing', function (): void {
    $report = new Report(['reason' => 'spam', 'status' => Status::Pending->value]);
    $report->save();

    $listener = new SyncCommentVisibilityFromReports;

    $listener->handleResolved(new ReportResolved($report));

    expect($report->reported)->toBeNull();
});
