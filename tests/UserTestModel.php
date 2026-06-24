<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\Tests;

use Illuminate\Foundation\Auth\User as Authenticatable;
use RoundlyConsulting\Comments\Traits\GivesComments;

class UserTestModel extends Authenticatable
{
    use GivesComments;

    public $table = 'users';

    protected $guarded = [];

    public $timestamps = false;
}
