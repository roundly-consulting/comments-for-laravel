<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\Tests\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use RoundlyConsulting\Comments\Tests\UlidKeyedTestModel;

/** @extends Factory<UlidKeyedTestModel> */
final class UlidKeyedTestModelFactory extends Factory
{
    protected $model = UlidKeyedTestModel::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [];
    }
}
