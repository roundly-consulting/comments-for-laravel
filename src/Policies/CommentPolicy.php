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
 *
 * The checks only run when `comments.authorization` is on. `create` receives the
 * subject being commented on (for a reply: the root subject the reply joins), so
 * a policy can decide per subject; `lock` / `unlock` receive what is being locked
 * — a subject, or a comment for a thread lock (`Comments::lockThread()`). `$user` is null for a guest.
 */
class CommentPolicy
{
    public function create(?Model $user, ?Model $commentable = null): bool
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

    public function restore(?Model $user, Comment $comment): bool
    {
        return true;
    }

    public function lock(?Model $user, Model $lockable): bool
    {
        return true;
    }

    public function unlock(?Model $user, Model $lockable): bool
    {
        return true;
    }
}
