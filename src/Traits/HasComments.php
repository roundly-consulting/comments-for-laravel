<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use RoundlyConsulting\Comments\Enums\CommentStatus;
use RoundlyConsulting\Comments\Models\Comment;
use RoundlyConsulting\Comments\Models\CommentLock;

trait HasComments
{
    /** @return MorphMany<Comment, $this> */
    public function comments(): MorphMany
    {
        /** @var class-string<Comment> $model */
        $model = config('comments.model', Comment::class);

        return $this->morphMany($model, 'commentable');
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
     * Eager-load replies for the subject's top-level comments, bounded by
     * `comments.max_depth` so very deep threads stay fast.
     *
     * @return MorphMany<Comment, $this>
     */
    public function threadedComments(): MorphMany
    {
        $maxDepth = (int) config('comments.max_depth', 5);

        return $this->comments()
            ->whereNull('parent_id')
            ->with($this->buildRepliesEagerLoad($maxDepth));
    }

    /**
     * Backward-compatible morph-based reply tree.
     *
     * @deprecated Prefer `threadedComments()` / the `replies` relation.
     *
     * @return MorphMany<Comment, $this>
     */
    public function commentsWithReplies(): MorphMany
    {
        return $this->comments()->with('commentsWithReplies');
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
     * Whether new/edited comments on this subject are blocked by a lock.
     */
    public function commentsLocked(): bool
    {
        return CommentLock::query()
            ->where('lockable_type', $this->getMorphClass())
            ->where('lockable_id', $this->getKey())
            ->exists();
    }

    private function buildRepliesEagerLoad(int $maxDepth): string
    {
        // A top-level comment is depth 1, so the eager-load nests max_depth - 1 reply levels.
        $levels = max(0, $maxDepth - 1);

        return rtrim(str_repeat('replies.', $levels), '.') ?: 'replies';
    }
}
