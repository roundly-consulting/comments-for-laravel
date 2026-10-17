<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\Exceptions;

final class InvalidCommentMetaPathException extends CommentsException
{
    public static function make(string $path): self
    {
        return new self(__('comments::comments.invalid_meta_path', ['path' => $path]));
    }
}
