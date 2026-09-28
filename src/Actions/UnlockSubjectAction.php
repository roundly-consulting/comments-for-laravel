<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\Actions;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Comments\Models\CommentLock;
use RoundlyConsulting\Comments\Support\CommentAuthorizer;
use RoundlyConsulting\Comments\Support\CommentModel;

final readonly class UnlockSubjectAction
{
    public function __construct(
        private CommentAuthorizer $authorizer,
    ) {}

    public function execute(Model $subject): void
    {
        $this->authorizer->authorize('unlock', [CommentModel::class(), $subject]);

        CommentLock::query()
            ->where('lockable_id', $subject->getKey())
            ->where('lockable_type', $subject->getMorphClass())
            ->delete();
    }
}
