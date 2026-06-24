<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\Traits;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use RoundlyConsulting\Comments\Enums\CommentStatus;
use RoundlyConsulting\Comments\Models\Comment;

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

    private function buildRepliesEagerLoad(int $maxDepth): string
    {
        // A top-level comment is depth 1, so the eager-load nests max_depth - 1 reply levels.
        $levels = max(0, $maxDepth - 1);

        return rtrim(str_repeat('replies.', $levels), '.') ?: 'replies';
    }
}
