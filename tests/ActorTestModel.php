<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\Tests;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Comments\Traits\GivesComments;

class ActorTestModel extends Model
{
    use GivesComments;

    public $table = 'actors';

    protected $guarded = [];

    public $timestamps = false;
}
