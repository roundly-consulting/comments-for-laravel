<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use RoundlyConsulting\Comments\Database\Factories\CommentMentionFactory;

/**
 * @property int $id
 * @property int $comment_id
 * @property string $handle
 * @property int|string|null $mentionable_id
 * @property string|null $mentionable_type
 * @property CarbonInterface|null $notified_at
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read Comment $comment
 * @property-read Model|null $mentionable
 */
class CommentMention extends Model
{
    /** @use HasFactory<CommentMentionFactory> */
    use HasFactory;

    protected $guarded = [];

    /** @return BelongsTo<Comment, $this> */
    public function comment(): BelongsTo
    {
        return $this->belongsTo(Comment::class);
    }

    /** @return MorphTo<Model, $this> */
    public function mentionable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Who this mention points at (`type|key`), so two handles resolving to the same person count
     * as one mention; null for a handle that resolved to no one.
     */
    public function mentionedIdentity(): ?string
    {
        $key = $this->mentionable_id;

        if ($this->mentionable_type === null || ! is_scalar($key)) {
            return null;
        }

        return $this->mentionable_type.'|'.$key;
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'notified_at' => 'datetime',
        ];
    }

    protected static function newFactory(): CommentMentionFactory
    {
        return CommentMentionFactory::new();
    }
}
