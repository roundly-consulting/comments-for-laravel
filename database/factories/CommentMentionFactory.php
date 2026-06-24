<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use RoundlyConsulting\Comments\Models\Comment;
use RoundlyConsulting\Comments\Models\CommentMention;

/** @extends Factory<CommentMention> */
final class CommentMentionFactory extends Factory
{
    protected $model = CommentMention::class;

    public function definition(): array
    {
        return [
            'comment_id' => Comment::factory(),
            'handle' => $this->faker->userName(),
        ];
    }
}
