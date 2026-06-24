<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Comments\Actions\ApproveCommentAction;
use RoundlyConsulting\Comments\Actions\DeleteCommentAction;
use RoundlyConsulting\Comments\Actions\HideCommentAction;
use RoundlyConsulting\Comments\Enums\CommentStatus;
use RoundlyConsulting\Comments\Models\Comment;

/**
 * A fluent read/moderation side mirroring the write builder. Scope it to a
 * subject (`for`) or an author (`byAuthor`), chain filters and ordering, then
 * fetch with `get`/`paginate` or moderate in bulk with `approveAll`/`hideAll`/
 * `deleteAll`.
 */
final class CommentQuery
{
    /** @var Builder<Comment> */
    private Builder $query;

    public function __construct()
    {
        /** @var class-string<Comment> $model */
        $model = config('comments.model', Comment::class);

        $this->query = $model::query();
    }

    public function for(Model $subject): self
    {
        $this->query
            ->where('commentable_type', $subject->getMorphClass())
            ->where('commentable_id', $subject->getKey());

        return $this;
    }

    public function byAuthor(Model $author): self
    {
        $this->query
            ->where('actor_type', $author->getMorphClass())
            ->where('actor_id', $author->getKey());

        return $this;
    }

    public function approved(): self
    {
        $this->query->where('status', CommentStatus::Approved);

        return $this;
    }

    public function pending(): self
    {
        $this->query->where('status', CommentStatus::Pending);

        return $this;
    }

    public function hidden(): self
    {
        $this->query->where('status', CommentStatus::Hidden);

        return $this;
    }

    public function visible(): self
    {
        $this->query->where('visible', true)->where('status', CommentStatus::Approved);

        return $this;
    }

    public function rootsOnly(): self
    {
        $this->query->whereNull('parent_id');

        return $this;
    }

    public function withReplies(): self
    {
        $maxDepth = (int) config('comments.max_depth', 5);
        $levels = max(0, $maxDepth - 1);
        $eager = rtrim(str_repeat('replies.', $levels), '.') ?: 'replies';

        $this->query->with($eager);

        return $this;
    }

    public function newest(): self
    {
        $this->query->orderBy('created_at', 'desc');

        return $this;
    }

    public function oldest(): self
    {
        $this->query->orderBy('created_at', 'asc');

        return $this;
    }

    /**
     * Escape hatch for arbitrary refinements on the underlying query.
     *
     * @param  callable(Builder<Comment>): void  $callback
     */
    public function tap(callable $callback): self
    {
        $callback($this->query);

        return $this;
    }

    /** @return Builder<Comment> */
    public function query(): Builder
    {
        return $this->query;
    }

    /** @return Collection<int, Comment> */
    public function get(): Collection
    {
        return $this->query->get();
    }

    /** @return LengthAwarePaginator<int, Comment> */
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->query->paginate($perPage);
    }

    public function count(): int
    {
        return $this->query->count();
    }

    /**
     * Approve every matching comment, firing CommentApproved per comment.
     */
    public function approveAll(): int
    {
        return $this->each(fn (Comment $comment) => app(ApproveCommentAction::class)->execute($comment));
    }

    /**
     * Hide every matching comment, firing CommentHidden per comment.
     */
    public function hideAll(): int
    {
        return $this->each(fn (Comment $comment) => app(HideCommentAction::class)->execute($comment));
    }

    /**
     * Soft-delete every matching comment, firing CommentDeleted per comment.
     */
    public function deleteAll(): int
    {
        return $this->each(fn (Comment $comment) => app(DeleteCommentAction::class)->execute($comment));
    }

    /**
     * @param  callable(Comment): mixed  $callback
     */
    private function each(callable $callback): int
    {
        $affected = 0;

        $this->query->clone()->each(function (Comment $comment) use ($callback, &$affected): void {
            $callback($comment);
            $affected++;
        });

        return $affected;
    }
}
