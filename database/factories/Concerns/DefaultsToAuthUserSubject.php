<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\Database\Factories\Concerns;

use Illuminate\Database\ClassMorphViolationException;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use LogicException;
use Throwable;

/**
 * The subject a factory row falls back to when it is given none: a new user of the default
 * guard's auth model, built through that model's own factory. The package ships no commentable
 * model, and the auth model is the one model every app has — and the one the `actor` morph
 * already has to fit, so its key suits `comments.key_type`. `recycle($user)` reuses a given
 * user instead.
 *
 * Use both halves as lazy definition values, type first, so a subject that cannot be named
 * fails before anything is inserted and an explicit subject replaces them before they run.
 */
trait DefaultsToAuthUserSubject
{
    /**
     * The exception explaining why there is no default subject and how to give one.
     */
    abstract protected function noSubject(string $reason, ?Throwable $previous = null): LogicException;

    /**
     * @return Factory<Model>
     */
    protected function defaultSubject(): Factory
    {
        $model = $this->authModel();
        $factory = [$model, 'factory'];

        // method_exists too: Model's __callStatic makes any static name look callable.
        if (! method_exists($model, 'factory') || ! is_callable($factory)) {
            throw $this->noSubject("the auth user model [{$model}] has no factory (it does not use HasFactory)");
        }

        try {
            $subject = $factory();
        } catch (Throwable $e) {
            throw $this->noSubject("[{$model}]::factory() failed: {$e->getMessage()}", $e);
        }

        if (! $subject instanceof Factory) {
            throw $this->noSubject("[{$model}]::factory() returned no Eloquent factory");
        }

        return $subject;
    }

    protected function defaultSubjectType(): string
    {
        try {
            return $this->defaultSubject()->newModel()->getMorphClass();
        } catch (ClassMorphViolationException $e) {
            throw $this->noSubject("the auth user model [{$e->model}] is not in the enforced morph map", $e);
        }
    }

    /**
     * The model of the default guard's user provider (`auth.providers.<provider>.model`).
     *
     * @return class-string<Model>
     */
    private function authModel(): string
    {
        $guard = config('auth.defaults.guard');
        $provider = is_string($guard) ? config("auth.guards.{$guard}.provider") : null;
        $model = is_string($provider) ? config("auth.providers.{$provider}.model") : null;

        if (! is_string($model) || ! is_subclass_of($model, Model::class)) {
            throw $this->noSubject('the default guard has no Eloquent auth user model');
        }

        return $model;
    }
}
