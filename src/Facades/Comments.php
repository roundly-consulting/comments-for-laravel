<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\Facades;

use Illuminate\Support\Facades\Facade;
use RoundlyConsulting\Comments\CommentsManager;
use RoundlyConsulting\Comments\Testing\CommentsFake;

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
 * @method static void assertPosted(\RoundlyConsulting\Comments\Models\Comment|callable|null $comment = null)
 * @method static void assertNothingPosted()
 * @method static void assertUpdated(\RoundlyConsulting\Comments\Models\Comment|callable|null $comment = null)
 * @method static void assertNothingUpdated()
 * @method static void assertDeleted(\RoundlyConsulting\Comments\Models\Comment|callable|null $comment = null)
 * @method static void assertNothingDeleted()
 * @method static void assertRestored(\RoundlyConsulting\Comments\Models\Comment|callable|null $comment = null)
 * @method static void assertNothingRestored()
 * @method static void assertApproved(\RoundlyConsulting\Comments\Models\Comment|callable|null $comment = null)
 * @method static void assertNothingApproved()
 * @method static void assertHidden(\RoundlyConsulting\Comments\Models\Comment|callable|null $comment = null)
 * @method static void assertNothingHidden()
 * @method static void assertLocked(\Illuminate\Database\Eloquent\Model|callable|null $subject = null)
 * @method static void assertNothingLocked()
 * @method static void assertUnlocked(\Illuminate\Database\Eloquent\Model|callable|null $subject = null)
 * @method static void assertNothingUnlocked()
 * @method static void assertThreadLocked(\RoundlyConsulting\Comments\Models\Comment|callable|null $comment = null)
 * @method static void assertNothingThreadLocked()
 * @method static void assertThreadUnlocked(\RoundlyConsulting\Comments\Models\Comment|callable|null $comment = null)
 * @method static void assertNothingThreadUnlocked()
 *
 * @see CommentsManager
 * @see CommentsFake
 */
final class Comments extends Facade
{
    /**
     * Swap the manager for a recording fake. Operations still run (rows, policies, locks,
     * events), while every mutation — through this facade, an injected manager, the builder, a
     * query's bulk moderation, the `GivesComments` trait or a `Comment` model method — is
     * recorded for the `assert*()` methods.
     */
    public static function fake(): CommentsFake
    {
        $fake = app(CommentsFake::class);

        self::swap($fake);

        return $fake;
    }

    protected static function getFacadeAccessor(): string
    {
        return CommentsManager::class;
    }
}
