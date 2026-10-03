<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\Support;

use RoundlyConsulting\Comments\Models\Comment;
use RoundlyConsulting\PackageToolkit\Support\ModelResolver;

/**
 * Resolves the Eloquent model backing comments from `comments.model`.
 *
 * Absent config resolves the packaged model; anything else must be that model or a subclass of
 * it, or the toolkit's ModelResolver throws InvalidConfigurationException naming the key — a
 * foreign class is never silently replaced.
 */
final class CommentModel
{
    /**
     * @return class-string<Comment>
     */
    public static function class(): string
    {
        return ModelResolver::for('comments.model', Comment::class);
    }
}
