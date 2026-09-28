<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\Exceptions;

final class InvalidCommentParentException extends CommentsException
{
    public static function foreignSubject(): self
    {
        return new self(__('comments::comments.foreign_parent'));
    }
}
