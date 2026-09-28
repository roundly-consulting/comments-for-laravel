<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Comments\Actions\LockThreadAction;
use RoundlyConsulting\Comments\Actions\UnlockThreadAction;
use RoundlyConsulting\Comments\Events\CommentThreadLocked;
use RoundlyConsulting\Comments\Events\CommentThreadUnlocked;
use RoundlyConsulting\Comments\Models\Comment;
use RoundlyConsulting\Comments\Tests\PostTestModel;

it('locks a thread and dispatches the locked event', function (): void {
    Event::fake([CommentThreadLocked::class]);
    $comment = Comment::factory()->for(PostTestModel::create(), 'commentable')->create();

    $locked = app(LockThreadAction::class)->execute($comment);

    expect($locked->fresh()?->locked_at)->not->toBeNull();
    Event::assertDispatched(CommentThreadLocked::class);
});

it('unlocks a thread and dispatches the unlocked event', function (): void {
    Event::fake([CommentThreadUnlocked::class]);
    $comment = Comment::factory()->for(PostTestModel::create(), 'commentable')->locked()->create();

    $unlocked = app(UnlockThreadAction::class)->execute($comment);

    expect($unlocked->fresh()?->locked_at)->toBeNull();
    Event::assertDispatched(CommentThreadUnlocked::class);
});
