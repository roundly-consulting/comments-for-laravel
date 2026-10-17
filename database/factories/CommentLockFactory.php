<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use LogicException;
use RoundlyConsulting\Comments\Database\Factories\Concerns\DefaultsToAuthUserSubject;
use RoundlyConsulting\Comments\Models\CommentLock;
use Throwable;

/** @extends Factory<CommentLock> */
final class CommentLockFactory extends Factory
{
    use DefaultsToAuthUserSubject;

    protected $model = CommentLock::class;

    public function definition(): array
    {
        return [
            // Lazy: on(), for() and explicit attributes replace both before they run.
            'lockable_type' => fn (): string => $this->defaultSubjectType(),
            'lockable_id' => fn (): Factory => $this->defaultSubject(),
        ];
    }

    /**
     * Lock the given subject. Without this state or `for($subject, 'lockable')`, a factory lock
     * is placed on a new user of the auth model.
     */
    public function on(Model $subject): self
    {
        return $this->state(fn (): array => [
            'lockable_id' => $subject->getKey(),
            'lockable_type' => $subject->getMorphClass(),
        ]);
    }

    protected function noSubject(string $reason, ?Throwable $previous = null): LogicException
    {
        return new LogicException(
            'A bare factory lock is placed on a new user of the auth model, but '.$reason.'. '
            .'Give the lock a subject: CommentLock::factory()->on($subject) or '
            ."->for(\$subject, 'lockable').",
            previous: $previous,
        );
    }
}
