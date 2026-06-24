<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\Actions;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Comments\Models\CommentLock;

final class LockSubjectAction
{
    public function execute(Model $subject): CommentLock
    {
        return CommentLock::query()->firstOrCreate([
            'lockable_id' => $subject->getKey(),
            'lockable_type' => $subject->getMorphClass(),
        ]);
    }
}
