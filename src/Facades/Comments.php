<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\Facades;

use Illuminate\Support\Facades\Facade;
use RoundlyConsulting\Comments\CommentManager;

/**
 * @method static \RoundlyConsulting\Comments\CommentBuilder on(\Illuminate\Database\Eloquent\Model $commentable)
 * @method static \RoundlyConsulting\Comments\Models\Comment write(\RoundlyConsulting\Comments\DataTransferObjects\WriteCommentData $data)
 * @method static \RoundlyConsulting\Comments\Models\Comment update(\RoundlyConsulting\Comments\Models\Comment $comment, string $body)
 * @method static void delete(\RoundlyConsulting\Comments\Models\Comment $comment)
 * @method static \RoundlyConsulting\Comments\Models\Comment restore(\RoundlyConsulting\Comments\Models\Comment $comment)
 * @method static \RoundlyConsulting\Comments\Models\Comment approve(\RoundlyConsulting\Comments\Models\Comment $comment)
 * @method static \RoundlyConsulting\Comments\Models\Comment hide(\RoundlyConsulting\Comments\Models\Comment $comment)
 *
 * @see CommentManager
 */
final class Comments extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return CommentManager::class;
    }
}
