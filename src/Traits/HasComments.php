<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use RoundlyConsulting\Comments\CommentsManager;
use RoundlyConsulting\Comments\Enums\CommentStatus;
use RoundlyConsulting\Comments\Models\Comment;
use RoundlyConsulting\Comments\Support\CommentModel;
use RoundlyConsulting\Comments\Support\CommentsConfig;
use RoundlyConsulting\Comments\Support\RepliesEagerLoad;

trait HasComments
{
    /** @return MorphMany<Comment, $this> */
    public function comments(): MorphMany
    {
        return $this->morphMany(CommentModel::class(), 'commentable');
    }

    /**
     * Visible + approved top-level comments, ordered per `comments.order`. With
     * `comments.deleted_comments = placeholder` a soft-deleted one keeps its place (render it
     * through `CommentResource`, or check `$comment->isPlaceholder()`).
     *
     * @return MorphMany<Comment, $this>
     */
    public function approvedComments(): MorphMany
    {
        $direction = CommentsConfig::order() === 'oldest' ? 'asc' : 'desc';

        return self::keepingPlaceholders($this->comments())
            ->whereNull('parent_id')
            ->where('visible', true)
            ->where('status', CommentStatus::Approved)
            ->orderBy('created_at', $direction);
    }

    /**
     * The public thread: visible + approved top-level comments with their visible + approved
     * replies eager-loaded, bounded by `comments.max_depth`. A hidden, pending or
     * `visible = false` comment never loads, and neither does anything below it — for a
     * moderation view of every reply use `Comments::for($subject)->rootsOnly()->withReplies()`.
     *
     * With `comments.deleted_comments = placeholder` a soft-deleted comment stays in the thread
     * at every level, with its replies; `CommentResource` renders it without its content.
     *
     * @return MorphMany<Comment, $this>
     */
    public function threadedComments(): MorphMany
    {
        $placeholders = CommentsConfig::deletedCommentsAsPlaceholders();

        return self::keepingPlaceholders($this->comments())
            ->whereNull('parent_id')
            ->where('visible', true)
            ->where('status', CommentStatus::Approved)
            ->with(RepliesEagerLoad::make(publicOnly: true, withDeleted: $placeholders));
    }

    /**
     * @param  MorphMany<Comment, $this>  $comments
     * @return MorphMany<Comment, $this>
     */
    private static function keepingPlaceholders(MorphMany $comments): MorphMany
    {
        if (CommentsConfig::deletedCommentsAsPlaceholders()) {
            $comments->getQuery()->withoutGlobalScope(SoftDeletingScope::class);
        }

        return $comments;
    }

    /**
     * Eager-load the public comment count onto this instance, exposed as the `comments_count`
     * attribute: every visible + approved comment, replies included — exactly what
     * `Comment::visible()` returns for the subject, so a public counter never reveals a hidden,
     * pending or `visible = false` comment.
     */
    public function loadCommentCount(): static
    {
        $this->loadCount(['comments as comments_count' => self::publicCommentCountConstraint(...)]);

        return $this;
    }

    /**
     * Query scope adding the public `comments_count` (see {@see self::loadCommentCount()})
     * without N+1.
     *
     * @param  Builder<static>  $query
     */
    public function scopeWithCommentCounts(Builder $query): void
    {
        $query->withCount(['comments as comments_count' => self::publicCommentCountConstraint(...)]);
    }

    /**
     * @param  Builder<Comment>  $query
     */
    private static function publicCommentCountConstraint(Builder $query): void
    {
        $query->where('visible', true)->where('status', CommentStatus::Approved);
    }

    /**
     * Whether new/edited comments on this subject are blocked by a lock — same as
     * `Comments::isLocked($subject)`.
     */
    public function commentsLocked(): bool
    {
        return app(CommentsManager::class)->isLocked($this);
    }
}
