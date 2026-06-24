<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\Enums;

enum CommentStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Hidden = 'hidden';
}
