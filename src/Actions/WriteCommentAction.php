<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\Actions;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Comments\DataTransferObjects\WriteCommentData;
use RoundlyConsulting\Comments\Enums\CommentStatus;
use RoundlyConsulting\Comments\Exceptions\CommentRejectedException;
use RoundlyConsulting\Comments\Exceptions\CommentsLockedException;
use RoundlyConsulting\Comments\Exceptions\InvalidCommentBodyException;
use RoundlyConsulting\Comments\Exceptions\MaxReplyDepthExceededException;
use RoundlyConsulting\Comments\Models\Comment;
use RoundlyConsulting\Comments\Models\CommentLock;
use RoundlyConsulting\Comments\Support\Blocklist;
use RoundlyConsulting\Comments\Support\CommentAuthorizer;
use RoundlyConsulting\Comments\Support\CommentModel;

final class WriteCommentAction
{
    public function __construct(
        private readonly Blocklist $blocklist,
        private readonly CommentAuthorizer $authorizer,
        private readonly SyncCommentMentionsAction $syncMentions,
    ) {}

    public function execute(WriteCommentData $data): Comment
    {
        // The Comment model class routes the check to the Comment policy's `create` ability
        // (a bare ability name would only ever hit a global gate of that name), and the
        // subject lets the policy decide per subject: `create(?Model $user, ?Model $commentable)`.
        $this->authorizer->authorize('create', [CommentModel::class(), $this->subject($data)]);

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

        $maxLength = (int) config('comments.max_length', 5000);

        if (mb_strlen($trimmed) > $maxLength) {
            throw InvalidCommentBodyException::tooLong($maxLength);
        }

        return $trimmed;
    }

    /**
     * Apply the moderation status, factoring in the blocklist. A blocklisted
     * body is either rejected outright or downgraded to a pending/hidden
     * status per `comments.blocklist_action`.
     */
    private function resolveStatus(string $body): CommentStatus
    {
        $default = (bool) config('comments.require_approval', false)
            ? CommentStatus::Pending
            : CommentStatus::Approved;

        if (! $this->blocklist->matches($body)) {
            return $default;
        }

        return match ((string) config('comments.blocklist_action', 'reject')) {
            'pending' => CommentStatus::Pending,
            'hidden' => CommentStatus::Hidden,
            default => throw CommentRejectedException::blocked(),
        };
    }

    private function guardLocked(WriteCommentData $data, string $commentableType, int|string $commentableId): void
    {
        // A locked reply chain blocks further replies to it.
        if ($data->parent instanceof Comment && $data->parent->isLocked()) {
            throw CommentsLockedException::make();
        }

        $locked = CommentLock::query()
            ->where('lockable_type', $commentableType)
            ->where('lockable_id', $commentableId)
            ->exists();

        if ($locked) {
            throw CommentsLockedException::make();
        }
    }

    /**
     * The model actually being commented on: a reply belongs to its parent's subject, not to
     * whatever subject the caller passed alongside it.
     */
    private function subject(WriteCommentData $data): ?Model
    {
        if (! $data->parent instanceof Comment) {
            return $data->commentable;
        }

        // Read through the relation loader rather than the `@property-read` docblock: a
        // subject deleted since the parent was written resolves to null, not a Model.
        $subject = $data->parent->getRelationValue('commentable');

        return $subject instanceof Model ? $subject : null;
    }

    /**
     * A reply inherits the parent's subject morph so replies stay attached to
     * the root subject (e.g. the post), keeping flat listings correct.
     *
     * The key is kept as the subject returns it: `comments.key_type` lets a host key its
     * subjects by uuid/ulid, and an integer cast would store `0` (or a digit prefix) for them.
     *
     * @return array{0: string, 1: int|string}
     */
    private function resolveSubject(WriteCommentData $data): array
    {
        if (! $data->parent instanceof Comment) {
            return [$data->commentable->getMorphClass(), $this->subjectKey($data->commentable)];
        }

        $this->guardReplyDepth($data->parent);

        return [$data->parent->commentable_type, $data->parent->commentable_id];
    }

    private function subjectKey(Model $subject): int|string
    {
        $key = $subject->getKey();

        return is_int($key) ? $key : (string) $key;
    }

    private function guardReplyDepth(Comment $parent): void
    {
        $maxDepth = (int) config('comments.max_depth', 5);

        $depth = 1;
        $current = $parent;

        while ($current->parent_id !== null) {
            $depth++;

            $loaded = $current->parent;

            if (! $loaded instanceof Model) {
                break;
            }

            $current = $loaded;
        }

        // The new comment sits one level below the parent.
        if ($depth + 1 > $maxDepth) {
            throw MaxReplyDepthExceededException::make($maxDepth);
        }
    }
}
