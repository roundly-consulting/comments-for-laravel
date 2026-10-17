<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use RoundlyConsulting\Comments\Actions\TransferCommentsAction;
use RoundlyConsulting\Comments\CommentsManager;
use RoundlyConsulting\Comments\Events\CommentsTransferred;
use RoundlyConsulting\Comments\Exceptions\CommentsLockedException;
use RoundlyConsulting\Comments\Exceptions\InvalidCommentTransferException;
use RoundlyConsulting\Comments\Exceptions\UnauthorizedCommentActionException;
use RoundlyConsulting\Comments\Facades\Comments;
use RoundlyConsulting\Comments\Models\Comment;
use RoundlyConsulting\Comments\Models\CommentLock;
use RoundlyConsulting\Comments\Models\CommentMention;
use RoundlyConsulting\Comments\Tests\ActorTestModel;
use RoundlyConsulting\Comments\Tests\DenyingCommentPolicy;
use RoundlyConsulting\Comments\Tests\PostTestModel;
use RoundlyConsulting\Comments\Tests\SubjectScopedCommentPolicy;
use RoundlyConsulting\Comments\Tests\UserTestModel;

/**
 * C-4 — `Comments::transfer($from, $to)`: re-point every comment of one subject at another
 * (merging two tickets, two duplicate posts, …).
 */
if (! function_exists('commentKeysOn')) {
    /**
     * Every comment row on the subject, soft-deleted ones included.
     *
     * @return list<int>
     */
    function commentKeysOn(PostTestModel $subject): array
    {
        return Comment::withTrashed()
            ->where('commentable_type', $subject->getMorphClass())
            ->where('commentable_id', $subject->getKey())
            ->orderBy('id')
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
    }
}

it('moves every comment of the source to the target and returns the count', function (): void {
    $from = PostTestModel::create();
    $to = PostTestModel::create();
    $author = ActorTestModel::create();

    $root = Comments::on($from)->as($author)->body('Root')->post();
    $reply = Comments::on($from)->as($author)->reply($root)->body('Reply')->post();
    $note = Comments::on($from)->as($author)->visible(false)->body('Internal')->post();
    $pending = Comment::factory()->on($from)->pending()->create();
    $hidden = Comment::factory()->on($from)->hidden()->create();
    $trashed = Comment::factory()->on($from)->create();
    $trashed->delete();
    $existing = Comment::factory()->on($to)->create();

    $moved = Comments::transfer($from, $to);

    expect($moved)->toBe(6)
        ->and(commentKeysOn($from))->toBe([])
        ->and(commentKeysOn($to))->toEqualCanonicalizing(array_map(
            fn (Comment $comment): int => (int) $comment->getKey(),
            [$root, $reply, $note, $pending, $hidden, $trashed, $existing],
        ))
        // The thread survives: parent links stay inside the moved set.
        ->and($reply->fresh()->parent_id)->toBe($root->getKey())
        ->and($reply->fresh()->parent->is($root))->toBeTrue()
        // Visibility and moderation state travel untouched.
        ->and($note->fresh()->visible)->toBeFalse()
        ->and($pending->fresh()->status->value)->toBe('pending')
        ->and($hidden->fresh()->status->value)->toBe('hidden')
        // A soft-deleted comment moves too, and restores onto the target.
        ->and(Comment::withTrashed()->find($trashed->getKey())->trashed())->toBeTrue();

    Comments::restore(Comment::withTrashed()->findOrFail($trashed->getKey()));

    expect($to->comments()->count())->toBe(7)
        ->and($from->comments()->count())->toBe(0);
});

it('carries mentions and thread locks with their comments', function (): void {
    $from = PostTestModel::create();
    $to = PostTestModel::create();

    $locked = Comment::factory()->on($from)->locked()->create();
    $mention = CommentMention::factory()->create(['comment_id' => $locked->getKey(), 'handle' => 'jane']);

    Comments::transfer($from, $to);

    expect($mention->fresh()->comment_id)->toBe($locked->getKey())
        ->and($locked->fresh()->isLocked())->toBeTrue()
        ->and(Comments::isThreadLocked($locked->fresh()))->toBeTrue()
        ->and($locked->fresh()->commentable->is($to))->toBeTrue();
});

it('keeps a subject lock on the source and allows transferring out of it', function (): void {
    $from = PostTestModel::create();
    $to = PostTestModel::create();
    Comment::factory()->on($from)->create();
    Comments::lock($from);

    expect(Comments::transfer($from, $to))->toBe(1)
        ->and(Comments::isLocked($from))->toBeTrue()
        ->and(Comments::isLocked($to))->toBeFalse()
        ->and(CommentLock::query()->count())->toBe(1);
});

it('leaves other subjects and authored comments alone', function (): void {
    $from = UserTestModel::create();
    $to = UserTestModel::create();
    $post = PostTestModel::create();

    // $from both receives a comment (it is the subject) and writes one on a post (the actor).
    $received = Comment::factory()->on($from)->create();
    $authored = $from->writeComment($post, 'Written by the source');
    $elsewhere = Comment::factory()->on($post)->create();

    expect(Comments::transfer($from, $to))->toBe(1)
        ->and($received->fresh()->commentable->is($to))->toBeTrue()
        ->and($authored->fresh()->commentable->is($post))->toBeTrue()
        ->and($authored->fresh()->actor->is($from))->toBeTrue()
        ->and($elsewhere->fresh()->commentable->is($post))->toBeTrue();
});

it('does not touch updated_at — a move is not an edit', function (): void {
    $from = PostTestModel::create();
    $to = PostTestModel::create();

    Carbon::setTestNow('2026-01-01 10:00:00');
    $comment = Comment::factory()->on($from)->create();

    Carbon::setTestNow('2026-06-01 10:00:00');
    Comments::transfer($from, $to);
    Carbon::setTestNow();

    expect($comment->fresh()->updated_at->toDateTimeString())->toBe('2026-01-01 10:00:00');
});

it('returns zero when the source has no comments', function (): void {
    expect(Comments::transfer(PostTestModel::create(), PostTestModel::create()))->toBe(0);
});

it('fires CommentsTransferred with both subjects and the count', function (): void {
    Event::fake([CommentsTransferred::class]);
    $from = PostTestModel::create();
    $to = PostTestModel::create();
    Comment::factory()->count(2)->on($from)->create();

    Comments::transfer($from, $to);

    Event::assertDispatched(
        CommentsTransferred::class,
        fn (CommentsTransferred $event): bool => $event->from->is($from) && $event->to->is($to) && $event->count === 2,
    );
});

it('refuses to transfer a subject onto itself', function (): void {
    $post = PostTestModel::create();
    Comment::factory()->on($post)->create();

    expect(fn () => Comments::transfer($post, $post))->toThrow(InvalidCommentTransferException::class)
        // Another instance of the same row is the same subject.
        ->and(fn () => Comments::transfer($post, PostTestModel::query()->findOrFail($post->getKey())))
        ->toThrow(InvalidCommentTransferException::class)
        ->and(commentKeysOn($post))->toHaveCount(1);
});

it('refuses a locked target and moves nothing', function (): void {
    Event::fake([CommentsTransferred::class]);
    $from = PostTestModel::create();
    $to = PostTestModel::create();
    Comment::factory()->on($from)->create();
    Comments::lock($to);

    expect(fn () => Comments::transfer($from, $to))->toThrow(CommentsLockedException::class)
        ->and(commentKeysOn($from))->toHaveCount(1)
        ->and(commentKeysOn($to))->toBe([]);

    Event::assertNotDispatched(CommentsTransferred::class);
});

it('authorizes the create ability on the target when authorization is on', function (): void {
    config()->set('comments.authorization', true);
    $this->actingAs(UserTestModel::create());
    Gate::policy(Comment::class, DenyingCommentPolicy::class);
    $from = PostTestModel::create();
    Comment::factory()->on($from)->create();

    expect(fn () => Comments::transfer($from, PostTestModel::create()))
        ->toThrow(UnauthorizedCommentActionException::class)
        ->and(commentKeysOn($from))->toHaveCount(1);
});

it('hands the target to the policy, not the source', function (): void {
    config()->set('comments.authorization', true);
    Gate::policy(Comment::class, SubjectScopedCommentPolicy::class);
    $from = PostTestModel::create();
    $open = PostTestModel::create();
    $closed = PostTestModel::create();
    Comment::factory()->on($from)->create();
    SubjectScopedCommentPolicy::$openSubjectKey = $open->getKey();

    try {
        expect(fn () => Comments::transfer($from, $closed))->toThrow(UnauthorizedCommentActionException::class)
            ->and(Comments::transfer($from, $open))->toBe(1);
    } finally {
        SubjectScopedCommentPolicy::$openSubjectKey = null;
    }
});

it('skips authorization when it is off', function (): void {
    config()->set('comments.authorization', false);
    Gate::policy(Comment::class, DenyingCommentPolicy::class);
    $from = PostTestModel::create();
    Comment::factory()->on($from)->create();

    expect(Comments::transfer($from, PostTestModel::create()))->toBe(1);
});

it('runs the same transfer through the manager and the action', function (): void {
    $from = PostTestModel::create();
    $to = PostTestModel::create();
    Comment::factory()->count(2)->on($from)->create();

    expect(app(CommentsManager::class)->transfer($from, $to))->toBe(2)
        ->and(app(TransferCommentsAction::class)->execute($to, $from))->toBe(2)
        ->and(commentKeysOn($from))->toHaveCount(2);
});
