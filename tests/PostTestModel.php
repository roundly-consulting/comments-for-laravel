<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\Tests;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Comments\Traits\HasComments;

class PostTestModel extends Model
{
    use HasComments;

    public $table = 'posts';

    protected $guarded = [];

    public $timestamps = false;
}
