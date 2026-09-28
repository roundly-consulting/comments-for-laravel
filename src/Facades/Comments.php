<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\Facades;

use Illuminate\Support\Facades\Facade;
use RoundlyConsulting\Comments\CommentsManager;

/**
 * @method static \RoundlyConsulting\Comments\CommentBuilder on(\Illuminate\Database\Eloquent\Model $commentable)
 * @method static \RoundlyConsulting\Comments\CommentQuery query()
 * @method static \RoundlyConsulting\Comments\CommentQuery for(\Illuminate\Database\Eloquent\Model $subject)
 * @method static \RoundlyConsulting\Comments\CommentQuery byAuthor(\Illuminate\Database\Eloquent\Model $author)
 * @method static \RoundlyConsulting\Comments\Models\Comment write(\RoundlyConsulting\Comments\DataTransferObjects\WriteCommentData $data)
 * @method static \RoundlyConsulting\Comments\Models\Comment update(\RoundlyConsulting\Comments\Models\Comment $comment, string $body)
 * @method static void delete(\RoundlyConsulting\Comments\Models\Comment $comment)
 * @method static \RoundlyConsulting\Comments\Models\Comment restore(\RoundlyConsulting\Comments\Models\Comment $comment)
 * @method static \RoundlyConsulting\Comments\Models\Comment approve(\RoundlyConsulting\Comments\Models\Comment $comment)
 * @method static \RoundlyConsulting\Comments\Models\Comment hide(\RoundlyConsulting\Comments\Models\Comment $comment)
 * @method static \RoundlyConsulting\Comments\Models\CommentLock lock(\Illuminate\Database\Eloquent\Model $subject)
 * @method static void unlock(\Illuminate\Database\Eloquent\Model $subject)
 * @method static bool isLocked(\Illuminate\Database\Eloquent\Model $subject)
 * @method static \RoundlyConsulting\Comments\Models\Comment lockThread(\RoundlyConsulting\Comments\Models\Comment $comment)
 * @method static \RoundlyConsulting\Comments\Models\Comment unlockThread(\RoundlyConsulting\Comments\Models\Comment $comment)
 * @method static bool isThreadLocked(\RoundlyConsulting\Comments\Models\Comment $comment)
 *
 * @see CommentsManager
 */
final class Comments extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return CommentsManager::class;
    }
}
