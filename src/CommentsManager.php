<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments;

use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Comments\Actions\ApproveCommentAction;
use RoundlyConsulting\Comments\Actions\DeleteCommentAction;
use RoundlyConsulting\Comments\Actions\HideCommentAction;
use RoundlyConsulting\Comments\Actions\LockSubjectAction;
use RoundlyConsulting\Comments\Actions\LockThreadAction;
use RoundlyConsulting\Comments\Actions\RestoreCommentAction;
use RoundlyConsulting\Comments\Actions\UnlockSubjectAction;
use RoundlyConsulting\Comments\Actions\UnlockThreadAction;
use RoundlyConsulting\Comments\Actions\UpdateCommentAction;
use RoundlyConsulting\Comments\Actions\WriteCommentAction;
use RoundlyConsulting\Comments\DataTransferObjects\UpdateCommentData;
use RoundlyConsulting\Comments\DataTransferObjects\WriteCommentData;
use RoundlyConsulting\Comments\Facades\Comments;
use RoundlyConsulting\Comments\Models\Comment;
use RoundlyConsulting\Comments\Models\CommentLock;
use RoundlyConsulting\Comments\Support\CommentLocks;

/**
 * The root behind the {@see Comments} facade — inject it for the same API without the facade.
 *
 * Every method is thin: it resolves one action from the container and runs it, so a host
 * binding over an action applies, and every write path (the builder, the query's bulk
 * moderation, the model traits and `Comment` methods) funnels through here — which is what lets
 * `Comments::fake()` see every call. Not `final`, because the fake is a subtype.
 */
class CommentsManager
{
    public function __construct(
        private readonly Container $container,
    ) {}

    /**
     * Start a fluent comment on the given subject.
     */
    public function on(Model $commentable): CommentBuilder
    {
        return new CommentBuilder($this, $commentable);
    }

    /**
     * Start a site-wide read/moderation query — every comment, on every subject.
     */
    public function query(): CommentQuery
    {
        return new CommentQuery($this);
    }

    /**
     * Start a read/moderation query scoped to a subject.
     */
    public function for(Model $subject): CommentQuery
    {
        return $this->query()->for($subject);
    }

    /**
     * Start a read/moderation query scoped to an author.
     */
    public function byAuthor(Model $author): CommentQuery
    {
        return $this->query()->byAuthor($author);
    }

    public function write(WriteCommentData $data): Comment
    {
        return $this->container->make(WriteCommentAction::class)->execute($data);
    }

    public function update(Comment $comment, string $body): Comment
    {
        return $this->container->make(UpdateCommentAction::class)
            ->execute(new UpdateCommentData(comment: $comment, body: $body));
    }

    public function delete(Comment $comment): void
    {
        $this->container->make(DeleteCommentAction::class)->execute($comment);
    }

    public function restore(Comment $comment): Comment
    {
        return $this->container->make(RestoreCommentAction::class)->execute($comment);
    }

    public function approve(Comment $comment): Comment
    {
        return $this->container->make(ApproveCommentAction::class)->execute($comment);
    }

    public function hide(Comment $comment): Comment
    {
        return $this->container->make(HideCommentAction::class)->execute($comment);
    }

    /**
     * Lock a subject so it stops accepting new or edited comments.
     */
    public function lock(Model $subject): CommentLock
    {
        return $this->container->make(LockSubjectAction::class)->execute($subject);
    }

    public function unlock(Model $subject): void
    {
        $this->container->make(UnlockSubjectAction::class)->execute($subject);
    }

    /**
     * Whether a subject is locked against new or edited comments.
     */
    public function isLocked(Model $subject): bool
    {
        return $this->container->make(CommentLocks::class)->subjectLocked($subject->getMorphClass(), $subject->getKey());
    }

    /**
     * Lock the thread under a comment: no new replies anywhere below it, and no edits to it or
     * any comment in it.
     */
    public function lockThread(Comment $comment): Comment
    {
        return $this->container->make(LockThreadAction::class)->execute($comment);
    }

    public function unlockThread(Comment $comment): Comment
    {
        return $this->container->make(UnlockThreadAction::class)->execute($comment);
    }

    /**
     * Whether a comment sits in a locked thread — it, or one of its ancestors, is thread-locked.
     */
    public function isThreadLocked(Comment $comment): bool
    {
        return $this->container->make(CommentLocks::class)->threadLocked($comment);
    }
}
