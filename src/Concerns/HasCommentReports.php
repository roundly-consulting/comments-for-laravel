<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\Concerns;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Reports\Traits\HasReports;

/**
 * First-class report-a-comment moderation for the bundled Comment model, built
 * on roundly-consulting/reports-for-laravel.
 *
 * Wires the comment into reports' `HasReports` seam so a comment can be flagged
 * (with dedup, typed reasons and guest reports), counted per status, and
 * surfaced in a moderation queue (`mostReported()`, `reportedMoreThan()`,
 * `withReportCounts()`). Because reports routes resolution through approvals,
 * comments inherit multi-moderator sign-off with no extra code.
 *
 * Reporters are always passed explicitly (e.g.
 * `Reports::report($comment)->by($user)->create()`); this package never resolves
 * the reporter implicitly from the auth guard.
 *
 * @mixin Model
 */
trait HasCommentReports
{
    use HasReports;
}
