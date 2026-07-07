<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\Tests;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Approvals\Interfaces\GivesApprovalsInterface;
use RoundlyConsulting\Approvals\Traits\GivesApprovals;

/**
 * A host moderator that can sign off on report-moderation requests through the
 * approvals engine (used by the multi-moderator visibility-sync test). Stored in
 * the `users` table.
 */
final class ModeratorTestModel extends Model implements GivesApprovalsInterface
{
    use GivesApprovals;

    public $table = 'users';

    public $timestamps = false;

    protected $guarded = [];
}
