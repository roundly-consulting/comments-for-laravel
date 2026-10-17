<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\Tests;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use RoundlyConsulting\Comments\Tests\Factories\UserTestModelFactory;
use RoundlyConsulting\Comments\Traits\GivesComments;

class UserTestModel extends Authenticatable
{
    use GivesComments;

    /** @use HasFactory<UserTestModelFactory> */
    use HasFactory;

    public $table = 'users';

    protected $guarded = [];

    public $timestamps = false;

    protected static function newFactory(): UserTestModelFactory
    {
        return UserTestModelFactory::new();
    }
}
