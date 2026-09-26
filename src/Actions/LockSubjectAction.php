<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\Actions;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Comments\Models\CommentLock;
use RoundlyConsulting\Comments\Support\CommentAuthorizer;
use RoundlyConsulting\Comments\Support\CommentModel;

final class LockSubjectAction
{
    public function __construct(
        private readonly CommentAuthorizer $authorizer,
    ) {}

    public function execute(Model $subject): CommentLock
    {
        // The Comment model class routes the check to the Comment policy's `lock` ability.
        $this->authorizer->authorize('lock', [CommentModel::class(), $subject]);

        return CommentLock::query()->firstOrCreate([
            'lockable_id' => $subject->getKey(),
            'lockable_type' => $subject->getMorphClass(),
        ]);
    }
}
