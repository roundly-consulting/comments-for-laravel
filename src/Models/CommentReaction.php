<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use RoundlyConsulting\Comments\Database\Factories\CommentReactionFactory;

/**
 * @property int $id
 * @property int $comment_id
 * @property int|null $reactor_id
 * @property string|null $reactor_type
 * @property string $reaction
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read Comment $comment
 * @property-read Model|null $reactor
 */
class CommentReaction extends Model
{
    /** @use HasFactory<CommentReactionFactory> */
    use HasFactory;

    protected $guarded = [];

    /** @return BelongsTo<Comment, $this> */
    public function comment(): BelongsTo
    {
        return $this->belongsTo(Comment::class);
    }

    /** @return MorphTo<Model, $this> */
    public function reactor(): MorphTo
    {
        return $this->morphTo();
    }

    protected static function newFactory(): CommentReactionFactory
    {
        return CommentReactionFactory::new();
    }
}
