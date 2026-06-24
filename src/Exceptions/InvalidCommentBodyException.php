<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\Exceptions;

final class InvalidCommentBodyException extends CommentsException
{
    public static function empty(): self
    {
        return new self(__('comments::comments.body_empty'));
    }

    public static function tooLong(int $maxLength): self
    {
        return new self(__('comments::comments.body_too_long', ['max' => $maxLength]));
    }
}
