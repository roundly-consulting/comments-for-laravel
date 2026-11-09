<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use RoundlyConsulting\Comments\Models\Comment;
use RoundlyConsulting\Comments\Models\CommentRevision;

/** @extends Factory<CommentRevision> */
final class CommentRevisionFactory extends Factory
{
    protected $model = CommentRevision::class;

    public function definition(): array
    {
        return [
            'comment_id' => Comment::factory(),
            'revision' => 1,
            'body' => $this->faker->sentence(),
        ];
    }
}
