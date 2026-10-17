<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\Database\Factories;

use Illuminate\Database\ClassMorphViolationException;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use LogicException;
use RoundlyConsulting\Comments\Enums\CommentStatus;
use RoundlyConsulting\Comments\Models\Comment;
use RoundlyConsulting\Comments\Support\CommentModel;
use Throwable;

/** @extends Factory<Comment> */
final class CommentFactory extends Factory
{
    protected $model = Comment::class;

    /**
     * Build the configured model, not the packaged one: a host that swaps
     * `comments.model` gets its own class, with its casts,
     * events and observers.
     *
     * @return class-string<Comment>
     */
    public function modelName(): string
    {
        return CommentModel::class();
    }

    public function definition(): array
    {
        return [
            'visible' => true,
            'status' => CommentStatus::Approved,
            'comment' => $this->faker->sentence(),
            // Lazy: on(), reply(), for() and explicit attributes replace both before they run,
            // so only a bare comment builds the default subject. The type goes first so a
            // subject that cannot be named fails before anything is inserted.
            'commentable_type' => fn (): string => $this->defaultSubjectType(),
            'commentable_id' => fn (): Factory => $this->defaultSubject(),
        ];
    }

    public function pending(): self
    {
        return $this->state(fn (): array => ['status' => CommentStatus::Pending]);
    }

    public function hidden(): self
    {
        return $this->state(fn (): array => ['status' => CommentStatus::Hidden]);
    }

    public function locked(): self
    {
        return $this->state(fn (): array => ['locked_at' => now()]);
    }

    /**
     * Give the comment metadata (the `meta` json column).
     *
     * @param  array<string, mixed>  $meta
     */
    public function meta(array $meta): self
    {
        return $this->state(fn (): array => ['meta' => $meta]);
    }

    /**
     * Write the comment on the given subject (the model being commented on). Without this state,
     * `reply($parent)` or `for($subject, 'commentable')`, a factory comment is written on a new
     * user of the auth model.
     */
    public function on(Model $subject): self
    {
        return $this->state(fn (): array => [
            'commentable_id' => $subject->getKey(),
            'commentable_type' => $subject->getMorphClass(),
        ]);
    }

    /**
     * Make this comment a reply to the given parent, inheriting its subject.
     */
    public function reply(Comment $parent): self
    {
        return $this->state(fn (): array => [
            'parent_id' => $parent->getKey(),
            'commentable_id' => $parent->commentable_id,
            'commentable_type' => $parent->commentable_type,
        ]);
    }

    /**
     * Attribute the comment to the given author model.
     */
    public function by(Model $author): self
    {
        return $this->state(fn (): array => [
            'actor_id' => $author->getKey(),
            'actor_type' => $author->getMorphClass(),
        ]);
    }

    /**
     * The subject of a bare comment: a new user of the default guard's auth model, built through
     * that model's own factory. The package ships no commentable model, and the auth model is
     * the one model every app has — and the one the `actor` morph already has to fit, so its
     * key suits `comments.key_type`. `recycle($user)` reuses a given user instead.
     *
     * @return Factory<Model>
     */
    private function defaultSubject(): Factory
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

    private function defaultSubjectType(): string
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

    private function noSubject(string $reason, ?Throwable $previous = null): LogicException
    {
        return new LogicException(
            'A bare factory comment is written on a new user of the auth model, but '.$reason.'. '
            .'Give the comment a subject: Comment::factory()->on($subject), ->reply($parent) or '
            ."->for(\$subject, 'commentable'); for a mention, "
            ."CommentMention::factory()->create(['comment_id' => Comment::factory()->on(\$subject)]).",
            previous: $previous,
        );
    }
}
