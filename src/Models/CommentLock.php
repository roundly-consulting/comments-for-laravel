<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use RoundlyConsulting\Comments\Database\Factories\CommentLockFactory;

/**
 * @property int $id
 * @property int $lockable_id
 * @property string $lockable_type
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read Model $lockable
 */
class CommentLock extends Model
{
    /** @use HasFactory<CommentLockFactory> */
    use HasFactory;

    protected $guarded = [];

    /** @return MorphTo<Model, $this> */
    public function lockable(): MorphTo
    {
        return $this->morphTo();
    }

    protected static function newFactory(): CommentLockFactory
    {
        return CommentLockFactory::new();
    }
}
