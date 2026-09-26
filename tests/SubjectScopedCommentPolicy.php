<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\Tests;

use Illuminate\Database\Eloquent\Model;

/**
 * A policy whose `create` decision depends on the subject being commented on — the shape a
 * host needs for "only members may comment on this project". It only works if the write
 * flow hands the subject to the policy.
 */
final class SubjectScopedCommentPolicy
{
    public static int|string|null $openSubjectKey = null;

    public function create(?Model $user, ?Model $commentable = null): bool
    {
        return $commentable !== null && $commentable->getKey() === self::$openSubjectKey;
    }

    public function lock(?Model $user, Model $lockable): bool
    {
        return $lockable->getKey() === self::$openSubjectKey;
    }

    public function unlock(?Model $user, Model $lockable): bool
    {
        return $lockable->getKey() === self::$openSubjectKey;
    }
}
