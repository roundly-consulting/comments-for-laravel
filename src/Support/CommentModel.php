<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\Support;

use RoundlyConsulting\Comments\Models\Comment;
use RoundlyConsulting\PackageToolkit\Support\ModelResolver;

/**
 * Resolves the Eloquent model backing comments from `comments.model`.
 *
 * The toolkit's ModelResolver validates that the configured value is a real
 * Eloquent model; anything that isn't a Comment (so it can't answer the
 * package's queries) falls back to the packaged model.
 */
final class CommentModel
{
    /**
     * @return class-string<Comment>
     */
    public static function class(): string
    {
        $model = ModelResolver::for('comments.model', Comment::class);

        return is_a($model, Comment::class, true) ? $model : Comment::class;
    }
}
