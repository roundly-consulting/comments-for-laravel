<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\Actions;

use RoundlyConsulting\Comments\DataTransferObjects\UpdateCommentData;
use RoundlyConsulting\Comments\Events\CommentUpdated;
use RoundlyConsulting\Comments\Exceptions\InvalidCommentBodyException;
use RoundlyConsulting\Comments\Models\Comment;

final class UpdateCommentAction
{
    public function execute(UpdateCommentData $data): Comment
    {
        $body = trim($data->body);

        if ($body === '') {
            throw InvalidCommentBodyException::empty();
        }

        $maxLength = (int) config('comments.max_length', 5000);

        if (mb_strlen($body) > $maxLength) {
            throw InvalidCommentBodyException::tooLong($maxLength);
        }

        $data->comment->update(['comment' => $body]);

        CommentUpdated::dispatch($data->comment);

        return $data->comment;
    }
}
