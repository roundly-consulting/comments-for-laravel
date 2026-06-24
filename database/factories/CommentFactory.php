<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Comments\Enums\CommentStatus;
use RoundlyConsulting\Comments\Models\Comment;

/** @extends Factory<Comment> */
final class CommentFactory extends Factory
{
    protected $model = Comment::class;

    public function definition(): array
    {
        return [
            'visible' => true,
            'status' => CommentStatus::Approved,
            'comment' => $this->faker->sentence(),
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
}
