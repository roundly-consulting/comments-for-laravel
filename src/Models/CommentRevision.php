<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use RoundlyConsulting\Comments\Database\Factories\CommentRevisionFactory;
use RoundlyConsulting\Comments\Support\CommentModel;

/**
 * One edit of a comment, kept while `comments.edit_history` is on: the body the edit replaced,
 * who made the edit (when the caller named an editor) and when (`created_at`). A comment's
 * revisions are numbered 1, 2, 3… in the order the edits landed; the current body lives on the
 * comment itself.
 *
 * @property int $id
 * @property int $comment_id
 * @property int $revision
 * @property string $body
 * @property int|string|null $editor_id
 * @property string|null $editor_type
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read Comment $comment
 * @property-read Model|null $editor
 */
class CommentRevision extends Model
{
    /** @use HasFactory<CommentRevisionFactory> */
    use HasFactory;

    protected $guarded = [];

    /**
     * The edited comment, resolved through the `comments.model` seam like
     * {@see CommentMention::comment()}.
     *
     * @return BelongsTo<Comment, $this>
     */
    public function comment(): BelongsTo
    {
        return $this->belongsTo(CommentModel::class(), 'comment_id');
    }

    /** @return MorphTo<Model, $this> */
    public function editor(): MorphTo
    {
        return $this->morphTo();
    }

    /** When the edit landed — the revision's `created_at`. */
    public function editedAt(): ?CarbonInterface
    {
        return $this->created_at;
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'revision' => 'integer',
        ];
    }

    protected static function newFactory(): CommentRevisionFactory
    {
        return CommentRevisionFactory::new();
    }
}
