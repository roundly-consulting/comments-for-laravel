<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\Tests\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use RoundlyConsulting\Comments\Tests\UserTestModel;

/**
 * The host's own user factory — what `App\Models\User::factory()` is in a Laravel app.
 *
 * @extends Factory<UserTestModel>
 */
final class UserTestModelFactory extends Factory
{
    protected $model = UserTestModel::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [];
    }
}
