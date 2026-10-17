<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\Tests\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use RoundlyConsulting\Comments\Tests\UuidKeyedTestModel;

/** @extends Factory<UuidKeyedTestModel> */
final class UuidKeyedTestModelFactory extends Factory
{
    protected $model = UuidKeyedTestModel::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [];
    }
}
