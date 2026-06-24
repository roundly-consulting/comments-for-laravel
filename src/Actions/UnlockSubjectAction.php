<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\Actions;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Comments\Models\CommentLock;

final class UnlockSubjectAction
{
    public function execute(Model $subject): void
    {
        CommentLock::query()
            ->where('lockable_id', $subject->getKey())
            ->where('lockable_type', $subject->getMorphClass())
            ->delete();
    }
}
