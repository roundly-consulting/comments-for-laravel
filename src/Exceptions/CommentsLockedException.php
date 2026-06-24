<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\Exceptions;

final class CommentsLockedException extends CommentsException
{
    public static function make(): self
    {
        return new self(__('comments::comments.locked'));
    }
}
