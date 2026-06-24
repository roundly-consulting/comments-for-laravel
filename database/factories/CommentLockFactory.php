<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use RoundlyConsulting\Comments\Models\CommentLock;

/** @extends Factory<CommentLock> */
final class CommentLockFactory extends Factory
{
    protected $model = CommentLock::class;

    public function definition(): array
    {
        return [
            'lockable_type' => 'post',
            'lockable_id' => 1,
        ];
    }
}
