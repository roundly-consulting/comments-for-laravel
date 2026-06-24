<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use RoundlyConsulting\Comments\Models\Comment;
use RoundlyConsulting\Comments\Models\CommentReaction;

/** @extends Factory<CommentReaction> */
final class CommentReactionFactory extends Factory
{
    protected $model = CommentReaction::class;

    public function definition(): array
    {
        return [
            'comment_id' => Comment::factory(),
            'reaction' => '👍',
        ];
    }
}
