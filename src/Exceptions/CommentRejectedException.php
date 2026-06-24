<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\Exceptions;

final class CommentRejectedException extends CommentsException
{
    public static function blocked(): self
    {
        return new self(__('comments::comments.rejected'));
    }
}
