<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\Tests;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * An auth model that uses `HasFactory` but has no factory class to resolve — a host whose
 * `Database\Factories\UserFactory` was deleted or never written.
 */
class FactorylessUserTestModel extends Authenticatable
{
    use HasFactory;

    public $table = 'users';

    protected $guarded = [];

    public $timestamps = false;
}
