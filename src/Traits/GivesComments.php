<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use RoundlyConsulting\Comments\CommentsManager;
use RoundlyConsulting\Comments\DataTransferObjects\WriteCommentData;
use RoundlyConsulting\Comments\Models\Comment;
use RoundlyConsulting\Comments\Support\CommentModel;

trait GivesComments
{
    /** @return MorphMany<Comment, $this> */
    public function writtenComments(): MorphMany
    {
        return $this->morphMany(CommentModel::class(), 'actor');
    }

    /**
     * Write a comment as this model — same as `Comments::on($commentable)->as($this)->…->post()`.
     */
    public function writeComment(Model $commentable, string $comment, bool $visible = true): Comment
    {
        return app(CommentsManager::class)->write(new WriteCommentData(
            commentable: $commentable,
            body: $comment,
            author: $this,
            visible: $visible,
        ));
    }
}
