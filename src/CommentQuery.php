<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Comments\Enums\CommentStatus;
use RoundlyConsulting\Comments\Models\Comment;
use RoundlyConsulting\Comments\Support\CommentModel;
use RoundlyConsulting\Comments\Support\RepliesEagerLoad;

/**
 * A fluent read/moderation side mirroring the write builder. Start it site-wide
 * (`Comments::query()`) or scoped to a subject (`Comments::for($post)`) or an author
 * (`Comments::byAuthor($user)`), chain filters and ordering, then fetch with
 * `get`/`paginate` or moderate in bulk with `approveAll`/`hideAll`/`deleteAll`.
 *
 * Bulk moderation runs each comment through the manager it came from, so a policy, the
 * per-comment events and `Comments::fake()` all see every row.
 */
final class CommentQuery
{
    /** @var Builder<Comment> */
    private Builder $query;

    private bool $withReplies = false;

    private bool $publicOnly = false;

    public function __construct(
        private readonly CommentsManager $manager,
    ) {
        $this->query = CommentModel::class()::query();
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

    /**
     * Only what the public sees: `visible` AND approved. With {@see self::withReplies()} (in
     * either order) the loaded replies follow the same rule at every level.
     */
    public function visible(): self
    {
        $this->query->where('visible', true)->where('status', CommentStatus::Approved);
        $this->publicOnly = true;

        return $this->loadReplies();
    }

    public function rootsOnly(): self
    {
        $this->query->whereNull('parent_id');

        return $this;
    }

    /**
     * Eager-load nested replies, bounded by `comments.max_depth`. On a {@see self::visible()}
     * query only public replies load (a hidden/pending/invisible reply and everything below it
     * are left out); without it every reply loads, for a moderation view.
     */
    public function withReplies(): self
    {
        $this->withReplies = true;

        return $this->loadReplies();
    }

    private function loadReplies(): self
    {
        if ($this->withReplies) {
            // Re-registering the `replies` key replaces the earlier constraint, so the
            // visibility rule holds whichever of visible() / withReplies() ran first.
            $this->query->with(RepliesEagerLoad::make($this->publicOnly));
        }

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
     * Rank the thread by like count, most-liked first (likes-for-laravel).
     */
    public function orderByLikesDesc(): self
    {
        $this->query->orderByLikesDesc();

        return $this;
    }

    /**
     * Rank the thread by a recency-weighted trending score (likes-for-laravel).
     */
    public function orderByTrending(): self
    {
        $this->query->orderByTrending();

        return $this;
    }

    /**
     * Hydrate per-row viewer like-state (`is_liked` / `liked_reaction`) for a whole
     * page in a single query. Pass the viewer explicitly; a null viewer renders a
     * guest state (never resolved from the auth guard).
     */
    public function withLikedState(?Model $viewer = null): self
    {
        $this->query->withLikedState($viewer);

        return $this;
    }

    /**
     * Eager-load each comment's report count for a moderation queue (reports-for-laravel).
     */
    public function withReportCounts(): self
    {
        $this->query->withReportCounts();

        return $this;
    }

    /**
     * Order the moderation queue by report volume, most-reported first.
     */
    public function mostReported(): self
    {
        $this->query->mostReported();

        return $this;
    }

    /**
     * Restrict the moderation queue to comments reported more than $threshold times.
     */
    public function reportedMoreThan(int $threshold): self
    {
        $this->query->reportedMoreThan($threshold);

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
        return $this->each(fn (Comment $comment): Comment => $this->manager->approve($comment));
    }

    /**
     * Hide every matching comment, firing CommentHidden per comment.
     */
    public function hideAll(): int
    {
        return $this->each(fn (Comment $comment): Comment => $this->manager->hide($comment));
    }

    /**
     * Soft-delete every matching comment, firing CommentDeleted per comment.
     */
    public function deleteAll(): int
    {
        return $this->each(function (Comment $comment): void {
            $this->manager->delete($comment);
        });
    }

    /**
     * @param  callable(Comment): mixed  $callback
     */
    private function each(callable $callback): int
    {
        $affected = 0;
        $model = $this->query->getModel();

        // Walk by primary key, not offset pages: the callback moves rows out of the filtered set
        // (approving a `pending()` match, deleting one), so offset pages would skip a page's
        // worth of matches after every chunk. The caller's ordering is irrelevant to a bulk
        // action and would fight the key cursor, so it is dropped.
        $this->query->clone()->reorder()->eachById(
            function (Comment $comment) use ($callback, &$affected): void {
                $callback($comment);
                $affected++;
            },
            1000,
            $model->getQualifiedKeyName(),
            $model->getKeyName(),
        );

        return $affected;
    }
}
