<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\Actions;

use RoundlyConsulting\Comments\DataTransferObjects\ReactToCommentData;
use RoundlyConsulting\Comments\Events\CommentUnreacted;
use RoundlyConsulting\Comments\Models\CommentReaction;

final class UnreactToCommentAction
{
    public function execute(ReactToCommentData $data): bool
    {
        $deleted = CommentReaction::query()
            ->where('comment_id', $data->comment->getKey())
            ->where('reactor_id', $data->reactor?->getKey())
            ->where('reactor_type', $data->reactor?->getMorphClass())
            ->where('reaction', $data->reaction)
            ->delete();

        if ($deleted === 0) {
            return false;
        }

        CommentUnreacted::dispatch($data->comment, $data->reaction, $data->reactor);

        return true;
    }
}
