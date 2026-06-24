<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\Tests;

use RoundlyConsulting\Comments\Models\Comment;

final class CustomCommentTestModel extends Comment
{
    public $table = 'comments';
}
