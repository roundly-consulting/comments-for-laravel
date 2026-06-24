<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\Exceptions;

final class MaxReplyDepthExceededException extends CommentsException
{
    public static function make(int $maxDepth): self
    {
        return new self(__('comments::comments.max_depth_exceeded', ['max' => $maxDepth]));
    }
}
