<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use RoundlyConsulting\Comments\Actions\ReactToCommentAction;
use RoundlyConsulting\Comments\Actions\UnreactToCommentAction;
use RoundlyConsulting\Comments\Database\Factories\CommentFactory;
use RoundlyConsulting\Comments\DataTransferObjects\ReactToCommentData;
use RoundlyConsulting\Comments\Enums\CommentStatus;
use RoundlyConsulting\Comments\Events\CommentCreated;
use RoundlyConsulting\Comments\Traits\HasComments;

/**
 * @property int $id
 * @property bool $visible
 * @property CommentStatus $status
 * @property int|null $parent_id
 * @property int|null $actor_id
 * @property string|null $actor_type
 * @property int $commentable_id
 * @property string $commentable_type
 * @property string $comment
 * @property CarbonInterface|null $locked_at
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property CarbonInterface|null $deleted_at
 * @property-read Model|null $actor
 * @property-read Model $commentable
 * @property-read Comment|null $parent
 * @property-read Collection<int, Comment> $replies
 * @property-read Collection<int, CommentReaction> $reactions
 * @property-read Collection<int, CommentMention> $mentions
 */
class Comment extends Model
{
    use HasComments;

    /** @use HasFactory<CommentFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $guarded = [];

    /** @var array<string, class-string> */
    protected $dispatchesEvents = [
        'created' => CommentCreated::class,
    ];

    /** @return MorphTo<Model, $this> */
    public function actor(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return MorphTo<Model, $this> */
    public function commentable(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<Comment, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<Comment, $this> */
    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /** @return HasMany<CommentReaction, $this> */
    public function reactions(): HasMany
    {
        return $this->hasMany(CommentReaction::class, 'comment_id');
    }

    /** @return HasMany<CommentMention, $this> */
    public function mentions(): HasMany
    {
        return $this->hasMany(CommentMention::class, 'comment_id');
    }

    /**
     * Add a reaction to this comment from the given reactor (or anonymously).
     */
    public function react(string $reaction, ?Model $as = null): CommentReaction
    {
        return app(ReactToCommentAction::class)->execute(
            new ReactToCommentData(comment: $this, reaction: $reaction, reactor: $as),
        );
    }

    /**
     * Remove a previously added reaction. Returns whether anything was removed.
     */
    public function unreact(string $reaction, ?Model $as = null): bool
    {
        return app(UnreactToCommentAction::class)->execute(
            new ReactToCommentData(comment: $this, reaction: $reaction, reactor: $as),
        );
    }

    /**
     * Counts keyed by reaction string, e.g. ['👍' => 3, '❤️' => 1].
     *
     * @return array<string, int>
     */
    public function reactionCounts(): array
    {
        $counts = [];

        foreach ($this->reactions()->get() as $reaction) {
            $counts[$reaction->reaction] = ($counts[$reaction->reaction] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * Lock this comment's reply chain so it cannot accept new or edited replies.
     */
    public function lockReplies(): self
    {
        $this->update(['locked_at' => now()]);

        return $this;
    }

    public function unlockReplies(): self
    {
        $this->update(['locked_at' => null]);

        return $this;
    }

    public function isLocked(): bool
    {
        return $this->locked_at !== null;
    }

    /**
     * Scope to comments whose moderation status is approved.
     *
     * @param  Builder<Comment>  $query
     */
    public function scopeApproved(Builder $query): void
    {
        $query->where('status', CommentStatus::Approved);
    }

    /**
     * Scope to comments still awaiting moderation.
     *
     * @param  Builder<Comment>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->where('status', CommentStatus::Pending);
    }

    /**
     * Scope to comments that have been hidden by a moderator.
     *
     * @param  Builder<Comment>  $query
     */
    public function scopeHidden(Builder $query): void
    {
        $query->where('status', CommentStatus::Hidden);
    }

    /**
     * Scope to comments shown to the public: visible AND approved.
     *
     * @param  Builder<Comment>  $query
     */
    public function scopeVisible(Builder $query): void
    {
        $query->where('visible', true)->where('status', CommentStatus::Approved);
    }

    /**
     * Scope to top-level comments (no parent).
     *
     * @param  Builder<Comment>  $query
     */
    public function scopeRoots(Builder $query): void
    {
        $query->whereNull('parent_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'visible' => 'boolean',
            'status' => CommentStatus::class,
            'locked_at' => 'datetime',
        ];
    }

    protected static function newFactory(): CommentFactory
    {
        return CommentFactory::new();
    }
}
