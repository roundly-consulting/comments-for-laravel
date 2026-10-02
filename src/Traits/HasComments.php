<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use RoundlyConsulting\Comments\CommentsManager;
use RoundlyConsulting\Comments\Enums\CommentStatus;
use RoundlyConsulting\Comments\Models\Comment;
use RoundlyConsulting\Comments\Support\CommentModel;
use RoundlyConsulting\Comments\Support\RepliesEagerLoad;

trait HasComments
{
    /** @return MorphMany<Comment, $this> */
    public function comments(): MorphMany
    {
        return $this->morphMany(CommentModel::class(), 'commentable');
    }

    /**
     * Visible + approved top-level comments, ordered per `comments.order`.
     *
     * @return MorphMany<Comment, $this>
     */
    public function approvedComments(): MorphMany
    {
        $direction = config('comments.order', 'latest') === 'oldest' ? 'asc' : 'desc';

        return $this->comments()
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
     * @return MorphMany<Comment, $this>
     */
    public function threadedComments(): MorphMany
    {
        return $this->comments()
            ->whereNull('parent_id')
            ->where('visible', true)
            ->where('status', CommentStatus::Approved)
            ->with(RepliesEagerLoad::make(publicOnly: true));
    }

    /**
     * Eager-load an approved comment count onto this instance, exposed as the
     * `comments_count` attribute.
     */
    public function loadCommentCount(): static
    {
        $this->loadCount(['comments as comments_count' => self::approvedCommentCountConstraint(...)]);

        return $this;
    }

    /**
     * Query scope adding an approved `comments_count` without N+1.
     *
     * @param  Builder<static>  $query
     */
    public function scopeWithCommentCounts(Builder $query): void
    {
        $query->withCount(['comments as comments_count' => self::approvedCommentCountConstraint(...)]);
    }

    /**
     * @param  Builder<Comment>  $query
     */
    private static function approvedCommentCountConstraint(Builder $query): void
    {
        $query->where('status', CommentStatus::Approved);
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
