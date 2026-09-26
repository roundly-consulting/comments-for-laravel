<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\Tests;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Comments\Traits\GivesComments;
use RoundlyConsulting\Comments\Traits\HasComments;

/**
 * A host model keyed by a ULID — both a comment subject and a commenter — for hosts that set
 * `comments.key_type` to `ulid`. Its table is created by the key-type tests themselves.
 *
 * @property string $id
 */
final class UlidKeyedTestModel extends Model
{
    use GivesComments;
    use HasComments;
    use HasUlids;

    public $table = 'ulid_keyed_models';

    protected $guarded = [];

    public $timestamps = false;
}
