<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\Support;

use Illuminate\Support\Facades\Gate;
use RoundlyConsulting\Comments\Exceptions\UnauthorizedCommentActionException;

final class CommentAuthorizer
{
    /**
     * Authorize a comment ability against Laravel's Gate (using the currently
     * authenticated user), but only when authorization enforcement is enabled
     * in config. When disabled (the default) every action is permitted so
     * existing behaviour is unchanged.
     *
     * @param  array<int, mixed>  $arguments
     */
    public function authorize(string $ability, array $arguments = []): void
    {
        if (! (bool) config('comments.authorization', false)) {
            return;
        }

        if (! Gate::allows($ability, $arguments)) {
            throw UnauthorizedCommentActionException::make();
        }
    }
}
