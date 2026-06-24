<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\Traits;

use Illuminate\Database\Eloquent\Relations\MorphMany;
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

    /** @return MorphMany<Comment, $this> */
    public function commentsWithReplies(): MorphMany
    {
        return $this->comments()->with('commentsWithReplies');
    }
}
