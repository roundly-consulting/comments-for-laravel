<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\Tests;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Comments\Models\Comment;

final class DenyingCommentPolicy
{
    public function create(?Model $user): bool
    {
        return false;
    }

    public function update(?Model $user, Comment $comment): bool
    {
        return false;
    }

    public function delete(?Model $user, Comment $comment): bool
    {
        return false;
    }

    public function moderate(?Model $user, Comment $comment): bool
    {
        return false;
    }
}
