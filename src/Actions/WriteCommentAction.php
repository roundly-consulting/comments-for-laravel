<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\Actions;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Comments\DataTransferObjects\WriteCommentData;
use RoundlyConsulting\Comments\Enums\CommentStatus;
use RoundlyConsulting\Comments\Exceptions\InvalidCommentBodyException;
use RoundlyConsulting\Comments\Exceptions\MaxReplyDepthExceededException;
use RoundlyConsulting\Comments\Models\Comment;

final class WriteCommentAction
{
    public function execute(WriteCommentData $data): Comment
    {
        $body = $this->validateBody($data->body);

        [$commentableType, $commentableId] = $this->resolveSubject($data);

        $status = (bool) config('comments.require_approval', false)
            ? CommentStatus::Pending
            : CommentStatus::Approved;

        /** @var class-string<Comment> $model */
        $model = config('comments.model', Comment::class);

        return $model::query()->create([
            'parent_id' => $data->parent?->getKey(),
            'actor_id' => $data->author?->getKey(),
            'actor_type' => $data->author?->getMorphClass(),
            'commentable_id' => $commentableId,
            'commentable_type' => $commentableType,
            'visible' => $data->visible,
            'status' => $status,
            'comment' => $body,
        ]);
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
