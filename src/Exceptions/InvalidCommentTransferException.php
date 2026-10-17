<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\Exceptions;

final class InvalidCommentTransferException extends CommentsException
{
    public static function sameSubject(): self
    {
        return new self(__('comments::comments.transfer_same_subject'));
    }
}
