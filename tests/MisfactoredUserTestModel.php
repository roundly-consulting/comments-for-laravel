<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\Tests;

use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * An auth model whose static `factory()` is not Eloquent's — it returns no factory.
 */
class MisfactoredUserTestModel extends Authenticatable
{
    public $table = 'users';

    public static function factory(): string
    {
        return 'not a factory';
    }
}
