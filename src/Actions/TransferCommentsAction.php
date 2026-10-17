<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\Actions;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Comments\Events\CommentsTransferred;
use RoundlyConsulting\Comments\Exceptions\CommentsLockedException;
use RoundlyConsulting\Comments\Exceptions\InvalidCommentTransferException;
use RoundlyConsulting\Comments\Support\CommentAuthorizer;
use RoundlyConsulting\Comments\Support\CommentLocks;
use RoundlyConsulting\Comments\Support\CommentModel;

/**
 * Move every comment of one subject onto another — merging two tickets, two duplicate posts —
 * and return how many moved.
 *
 * Only the subject morph of each row changes, and nothing else keys on the subject: replies point
 * at parents that move with them, mentions / likes / reports / attachments belong to the comment,
 * and a thread lock (`locked_at`) is on the comment. A subject lock (`comment_locks`) stays on
 * `$from`. Soft-deleted comments move too, so a later restore lands on `$to`.
 *
 * Refuses the same subject and a locked target, and authorizes the policy's existing `create`
 * ability on `$to` when `comments.authorization` is on — moving comments onto a subject is
 * commenting on it, and a host policy needs no new method.
 */
final readonly class TransferCommentsAction
{
    public function __construct(
        private CommentAuthorizer $authorizer,
        private CommentLocks $locks,
    ) {}

    /**
     * @throws InvalidCommentTransferException when `$from` and `$to` are the same subject
     * @throws CommentsLockedException when `$to` is locked
     */
    public function execute(Model $from, Model $to): int
    {
        $fromType = $from->getMorphClass();
        $fromKey = $this->key($from);
        $toType = $to->getMorphClass();
        $toKey = $this->key($to);

        if ($fromType === $toType && (string) $fromKey === (string) $toKey) {
            throw InvalidCommentTransferException::sameSubject();
        }

        $this->authorizer->authorize('create', [CommentModel::class(), $to]);

        $query = CommentModel::class()::query();

        $count = $query->getConnection()->transaction(function () use ($query, $fromType, $fromKey, $toType, $toKey): int {
            if ($this->locks->subjectLocked($toType, $toKey)) {
                throw CommentsLockedException::make();
            }

            // A base-query update: a move is not an edit, so `updated_at` stays, and the rows go in
            // one statement whatever their status, visibility or soft-delete state.
            return $query->withTrashed()
                ->where('commentable_type', $fromType)
                ->where('commentable_id', $fromKey)
                ->toBase()
                ->update([
                    'commentable_type' => $toType,
                    'commentable_id' => $toKey,
                ]);
        });

        CommentsTransferred::dispatch($from, $to, $count);

        return $count;
    }

    /**
     * The subject key as the rows store it: kept as the subject returns it, because
     * `comments.key_type` may be uuid/ulid and an integer cast would squash those.
     */
    private function key(Model $subject): int|string
    {
        $key = $subject->getKey();

        return is_int($key) ? $key : (string) $key;
    }
}
