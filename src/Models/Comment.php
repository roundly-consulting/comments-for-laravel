<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use RoundlyConsulting\Comments\Database\Factories\CommentFactory;
use RoundlyConsulting\Comments\Events\CommentCreated;
use RoundlyConsulting\Comments\Traits\HasComments;

/**
 * @property int $id
 * @property bool $visible
 * @property int $actor_id
 * @property string $actor_type
 * @property int $commentable_id
 * @property string $commentable_type
 * @property string $comment
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property CarbonInterface|null $deleted_at
 * @property-read Model $actor
 * @property-read Model $commentable
 */
final class Comment extends Model
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

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'visible' => 'boolean',
        ];
    }

    protected static function newFactory(): CommentFactory
    {
        return CommentFactory::new();
    }
}
