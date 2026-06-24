<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
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
}
