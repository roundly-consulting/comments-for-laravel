<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\Enums;

use RoundlyConsulting\Enums\Helpers;

enum CommentStatus: string
{
    use Helpers;

    case Pending = 'pending';
    case Approved = 'approved';
    case Hidden = 'hidden';
}
