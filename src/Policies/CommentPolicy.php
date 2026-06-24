<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\Policies;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Comments\Models\Comment;

/**
 * A permissive starting-point policy. Register it for the Comment model and
 * override the methods to gate who may comment, edit, or moderate. Every
 * ability returns true by default so opting in does not change behaviour until
 * you customise it.
 */
class CommentPolicy
{
    public function create(?Model $user): bool
    {
        return true;
    }

    public function update(?Model $user, Comment $comment): bool
    {
        return true;
    }

    public function delete(?Model $user, Comment $comment): bool
    {
        return true;
    }

    public function moderate(?Model $user, Comment $comment): bool
    {
        return true;
    }
}
