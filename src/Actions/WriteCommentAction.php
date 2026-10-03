<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\Actions;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Comments\DataTransferObjects\WriteCommentData;
use RoundlyConsulting\Comments\Enums\CommentStatus;
use RoundlyConsulting\Comments\Exceptions\CommentsLockedException;
use RoundlyConsulting\Comments\Exceptions\InvalidCommentBodyException;
use RoundlyConsulting\Comments\Exceptions\InvalidCommentParentException;
use RoundlyConsulting\Comments\Exceptions\MaxReplyDepthExceededException;
use RoundlyConsulting\Comments\Models\Comment;
use RoundlyConsulting\Comments\Support\Blocklist;
use RoundlyConsulting\Comments\Support\CommentAncestry;
use RoundlyConsulting\Comments\Support\CommentAuthorizer;
use RoundlyConsulting\Comments\Support\CommentLocks;
use RoundlyConsulting\Comments\Support\CommentModel;
use RoundlyConsulting\Comments\Support\CommentsConfig;
use RoundlyConsulting\PackageToolkit\Support\Config;

final readonly class WriteCommentAction
{
    public function __construct(
        private Blocklist $blocklist,
        private CommentAuthorizer $authorizer,
        private CommentLocks $locks,
        private CommentAncestry $ancestry,
        private SyncCommentMentionsAction $syncMentions,
    ) {}

    public function execute(WriteCommentData $data): Comment
    {
        // A reply joins its parent's subject, so a parent from another subject is refused up
        // front — the subject passed in is then the one the policy and the row both see.
        if ($data->parent instanceof Comment) {
            $this->guardParentSubject($data->parent, $data->commentable);
        }

        // The Comment model class routes the check to the Comment policy's `create` ability
        // (a bare ability name would only ever hit a global gate of that name), and the
        // subject lets the policy decide per subject: `create(?Model $user, ?Model $commentable)`.
        $this->authorizer->authorize('create', [CommentModel::class(), $data->commentable]);

        $body = $this->validateBody($data->body);

        [$commentableType, $commentableId] = $this->resolveSubject($data);

        $this->guardLocked($data, $commentableType, $commentableId);

        $status = $this->resolveStatus($body);

        $comment = CommentModel::class()::query()->create([
            'parent_id' => $data->parent?->getKey(),
            'actor_id' => $data->author?->getKey(),
            'actor_type' => $data->author?->getMorphClass(),
            'commentable_id' => $commentableId,
            'commentable_type' => $commentableType,
            'visible' => $data->visible,
            'status' => $status,
            'comment' => $body,
        ]);

        $this->syncMentions->execute($comment);

        return $comment;
    }

    private function validateBody(string $body): string
    {
        $trimmed = trim($body);

        if ($trimmed === '') {
            throw InvalidCommentBodyException::empty();
        }

        $maxLength = CommentsConfig::maxLength();

        if (mb_strlen($trimmed) > $maxLength) {
            throw InvalidCommentBodyException::tooLong($maxLength);
        }

        return $trimmed;
    }

    /**
     * The moderation status a new comment starts at: held per `comments.blocklist_action` when
     * the body is blocklisted (or rejected outright), otherwise per `comments.require_approval`.
     */
    private function resolveStatus(string $body): CommentStatus
    {
        $default = Config::boolean('comments.require_approval')
            ? CommentStatus::Pending
            : CommentStatus::Approved;

        return $this->blocklist->heldStatus($body) ?? $default;
    }

    private function guardLocked(WriteCommentData $data, string $commentableType, int|string $commentableId): void
    {
        // A thread lock on the parent or any of its ancestors blocks replies anywhere below it.
        if ($data->parent instanceof Comment && $this->locks->threadLocked($data->parent)) {
            throw CommentsLockedException::make();
        }

        if ($this->locks->subjectLocked($commentableType, $commentableId)) {
            throw CommentsLockedException::make();
        }
    }

    /**
     * The subject morph the row is stored under. A reply sits on its parent's subject (checked
     * by {@see self::guardParentSubject()}), so replies stay attached to the root subject (e.g.
     * the post), keeping flat listings correct.
     *
     * The key is kept as the subject returns it: `comments.key_type` lets a host key its
     * subjects by uuid/ulid, and an integer cast would store `0` (or a digit prefix) for them.
     *
     * @return array{0: string, 1: int|string}
     */
    private function resolveSubject(WriteCommentData $data): array
    {
        if ($data->parent instanceof Comment) {
            $this->guardReplyDepth($data->parent);
        }

        return [$data->commentable->getMorphClass(), $this->subjectKey($data->commentable)];
    }

    /**
     * The subject a reply is written on is a scope, not a hint: a parent that belongs to another
     * subject is refused rather than silently re-targeting the reply onto that other subject.
     */
    private function guardParentSubject(Comment $parent, Model $commentable): void
    {
        $sameSubject = $parent->commentable_type === $commentable->getMorphClass()
            && (string) $parent->commentable_id === (string) $this->subjectKey($commentable);

        if (! $sameSubject) {
            throw InvalidCommentParentException::foreignSubject();
        }
    }

    private function subjectKey(Model $subject): int|string
    {
        $key = $subject->getKey();

        return is_int($key) ? $key : (string) $key;
    }

    /**
     * The new comment sits one level below its parent. Soft-deleted ancestors count (see
     * {@see CommentAncestry}), so deleting a comment mid-thread does not make room below it.
     */
    private function guardReplyDepth(Comment $parent): void
    {
        $maxDepth = CommentsConfig::maxDepth();

        if ($this->ancestry->depthOf($parent) + 1 > $maxDepth) {
            throw MaxReplyDepthExceededException::make($maxDepth);
        }
    }
}
