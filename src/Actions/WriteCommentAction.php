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

final class WriteCommentAction
{
    public function __construct(
        private readonly Blocklist $blocklist,
        private readonly CommentAuthorizer $authorizer,
        private readonly SyncCommentMentionsAction $syncMentions,
    ) {}

    public function execute(WriteCommentData $data): Comment
    {
        $this->authorizer->authorize('create');

        $body = $this->validateBody($data->body);

        [$commentableType, $commentableId] = $this->resolveSubject($data);

        $this->guardLocked($data, $commentableType, $commentableId);

        $status = $this->resolveStatus($body);

        /** @var class-string<Comment> $model */
        $model = config('comments.model', Comment::class);

        $comment = $model::query()->create([
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

    private function guardLocked(WriteCommentData $data, string $commentableType, int $commentableId): void
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
     * A reply inherits the parent's subject morph so replies stay attached to
     * the root subject (e.g. the post), keeping flat listings correct.
     *
     * @return array{0: string, 1: int}
     */
    private function resolveSubject(WriteCommentData $data): array
    {
        if (! $data->parent instanceof Comment) {
            return [$data->commentable->getMorphClass(), (int) $data->commentable->getKey()];
        }

        $this->guardReplyDepth($data->parent);

        return [$data->parent->commentable_type, $data->parent->commentable_id];
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
