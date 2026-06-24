<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\Actions;

use RoundlyConsulting\Comments\DataTransferObjects\ReactToCommentData;
use RoundlyConsulting\Comments\Events\CommentReacted;
use RoundlyConsulting\Comments\Models\CommentReaction;

final class ReactToCommentAction
{
    public function execute(ReactToCommentData $data): CommentReaction
    {
        $reaction = CommentReaction::query()->firstOrCreate([
            'comment_id' => $data->comment->getKey(),
            'reactor_id' => $data->reactor?->getKey(),
            'reactor_type' => $data->reactor?->getMorphClass(),
            'reaction' => $data->reaction,
        ]);

        if ($reaction->wasRecentlyCreated) {
            CommentReacted::dispatch($reaction);
        }

        return $reaction;
    }
}
