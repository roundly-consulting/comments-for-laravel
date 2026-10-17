<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use RoundlyConsulting\Comments\Database\Factories\CommentMentionFactory;
use RoundlyConsulting\Comments\Support\CommentModel;

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

    /**
     * The mentioned-in comment, resolved through the `comments.model` seam like
     * {@see Comment::parent()}, so a host that swapped the model reads its own class back.
     *
     * @return BelongsTo<Comment, $this>
     */
    public function comment(): BelongsTo
    {
        return $this->belongsTo(CommentModel::class(), 'comment_id');
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
