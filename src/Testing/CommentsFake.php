<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\Testing;

use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Assert;
use RoundlyConsulting\Comments\CommentsManager;
use RoundlyConsulting\Comments\DataTransferObjects\WriteCommentData;
use RoundlyConsulting\Comments\Facades\Comments;
use RoundlyConsulting\Comments\Models\Comment;
use RoundlyConsulting\Comments\Models\CommentLock;

/**
 * The recording, still-performing stand-in {@see Comments::fake()} swaps in.
 *
 * Every operation runs as usual — rows are written, policies and locks apply, events fire — so
 * reads and listeners behave normally, while each successful mutation is recorded, from
 * wherever it came: the facade, an injected {@see CommentsManager}, the `on()->post()`
 * builder, a query's `approveAll()` / `hideAll()` / `deleteAll()`, the `GivesComments` trait or
 * a `Comment` model method (`lockReplies()` / `unlockReplies()`). A call that throws records
 * nothing.
 *
 * Each `assert*()` takes an optional expectation: a model (matched with `is()`), or a callback
 * called with the recorded comment — or, for subject locks, the recorded subject — that returns
 * true on a match. Without one, any recorded call passes.
 *
 * This class lives in src/ so host apps can use it; it depends on PHPUnit's Assert, which is
 * always present in a Laravel app's dev dependencies.
 */
final class CommentsFake extends CommentsManager
{
    /** @var array<string, list<Model>> */
    private array $recorded = [];

    public function write(WriteCommentData $data): Comment
    {
        return $this->record('posted', parent::write($data));
    }

    public function update(Comment $comment, string $body): Comment
    {
        return $this->record('updated', parent::update($comment, $body));
    }

    public function delete(Comment $comment): void
    {
        parent::delete($comment);

        $this->record('deleted', $comment);
    }

    public function restore(Comment $comment): Comment
    {
        return $this->record('restored', parent::restore($comment));
    }

    public function approve(Comment $comment): Comment
    {
        return $this->record('approved', parent::approve($comment));
    }

    public function hide(Comment $comment): Comment
    {
        return $this->record('hidden', parent::hide($comment));
    }

    public function lock(Model $subject): CommentLock
    {
        $lock = parent::lock($subject);

        $this->record('locked', $subject);

        return $lock;
    }

    public function unlock(Model $subject): void
    {
        parent::unlock($subject);

        $this->record('unlocked', $subject);
    }

    public function lockThread(Comment $comment): Comment
    {
        return $this->record('threadLocked', parent::lockThread($comment));
    }

    public function unlockThread(Comment $comment): Comment
    {
        return $this->record('threadUnlocked', parent::unlockThread($comment));
    }

    /**
     * @param  Comment|(callable(Comment): bool)|null  $comment
     */
    public function assertPosted(Comment|callable|null $comment = null): void
    {
        $this->assertRecorded('posted', 'a comment to be posted', $comment);
    }

    public function assertNothingPosted(): void
    {
        $this->assertNothingRecorded('posted', 'no comment to be posted');
    }

    /**
     * @param  Comment|(callable(Comment): bool)|null  $comment
     */
    public function assertUpdated(Comment|callable|null $comment = null): void
    {
        $this->assertRecorded('updated', 'a comment to be updated', $comment);
    }

    public function assertNothingUpdated(): void
    {
        $this->assertNothingRecorded('updated', 'no comment to be updated');
    }

    /**
     * @param  Comment|(callable(Comment): bool)|null  $comment
     */
    public function assertDeleted(Comment|callable|null $comment = null): void
    {
        $this->assertRecorded('deleted', 'a comment to be deleted', $comment);
    }

    public function assertNothingDeleted(): void
    {
        $this->assertNothingRecorded('deleted', 'no comment to be deleted');
    }

    /**
     * @param  Comment|(callable(Comment): bool)|null  $comment
     */
    public function assertRestored(Comment|callable|null $comment = null): void
    {
        $this->assertRecorded('restored', 'a comment to be restored', $comment);
    }

    public function assertNothingRestored(): void
    {
        $this->assertNothingRecorded('restored', 'no comment to be restored');
    }

    /**
     * @param  Comment|(callable(Comment): bool)|null  $comment
     */
    public function assertApproved(Comment|callable|null $comment = null): void
    {
        $this->assertRecorded('approved', 'a comment to be approved', $comment);
    }

    public function assertNothingApproved(): void
    {
        $this->assertNothingRecorded('approved', 'no comment to be approved');
    }

    /**
     * @param  Comment|(callable(Comment): bool)|null  $comment
     */
    public function assertHidden(Comment|callable|null $comment = null): void
    {
        $this->assertRecorded('hidden', 'a comment to be hidden', $comment);
    }

    public function assertNothingHidden(): void
    {
        $this->assertNothingRecorded('hidden', 'no comment to be hidden');
    }

    /**
     * @param  Model|(callable(Model): bool)|null  $subject
     */
    public function assertLocked(Model|callable|null $subject = null): void
    {
        $this->assertRecorded('locked', 'a subject to be locked', $subject);
    }

    public function assertNothingLocked(): void
    {
        $this->assertNothingRecorded('locked', 'no subject to be locked');
    }

    /**
     * @param  Model|(callable(Model): bool)|null  $subject
     */
    public function assertUnlocked(Model|callable|null $subject = null): void
    {
        $this->assertRecorded('unlocked', 'a subject to be unlocked', $subject);
    }

    public function assertNothingUnlocked(): void
    {
        $this->assertNothingRecorded('unlocked', 'no subject to be unlocked');
    }

    /**
     * @param  Comment|(callable(Comment): bool)|null  $comment
     */
    public function assertThreadLocked(Comment|callable|null $comment = null): void
    {
        $this->assertRecorded('threadLocked', 'a thread to be locked', $comment);
    }

    public function assertNothingThreadLocked(): void
    {
        $this->assertNothingRecorded('threadLocked', 'no thread to be locked');
    }

    /**
     * @param  Comment|(callable(Comment): bool)|null  $comment
     */
    public function assertThreadUnlocked(Comment|callable|null $comment = null): void
    {
        $this->assertRecorded('threadUnlocked', 'a thread to be unlocked', $comment);
    }

    public function assertNothingThreadUnlocked(): void
    {
        $this->assertNothingRecorded('threadUnlocked', 'no thread to be unlocked');
    }

    /**
     * @template TModel of Model
     *
     * @param  TModel  $model
     * @return TModel
     */
    private function record(string $kind, Model $model): Model
    {
        $this->recorded[$kind][] = $model;

        return $model;
    }

    private function assertRecorded(string $kind, string $expectation, Model|callable|null $expected): void
    {
        $calls = $this->recorded[$kind] ?? [];

        if ($expected === null) {
            Assert::assertNotEmpty($calls, "Expected {$expectation}, but none was.");

            return;
        }

        $matched = false;

        foreach ($calls as $call) {
            if ($expected instanceof Model ? $call->is($expected) : $expected($call) === true) {
                $matched = true;

                break;
            }
        }

        $what = $expected instanceof Model ? 'the given model' : 'the callback';

        Assert::assertTrue($matched, "Expected {$expectation} matching {$what}, but none did.");
    }

    private function assertNothingRecorded(string $kind, string $expectation): void
    {
        $count = count($this->recorded[$kind] ?? []);

        Assert::assertSame(0, $count, "Expected {$expectation}, but {$count} were.");
    }
}
