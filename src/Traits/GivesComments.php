<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use RoundlyConsulting\Comments\Models\Comment;

trait GivesComments
{
    /** @return MorphMany<Comment, $this> */
    public function writtenComments(): MorphMany
    {
        /** @var class-string<Comment> $model */
        $model = config('comments.model', Comment::class);

        return $this->morphMany($model, 'actor');
    }

    public function writeComment(Model $commentable, string $comment, bool $visible = true): Comment
    {
        return $this->writtenComments()
            ->create([
                'commentable_id' => $commentable->getKey(),
                'commentable_type' => $commentable->getMorphClass(),
                'visible' => $visible,
                'comment' => $comment,
            ]);
    }
}
