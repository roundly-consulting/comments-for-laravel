<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\Listeners;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Comments\Enums\CommentStatus;
use RoundlyConsulting\Comments\Events\CommentHidden;
use RoundlyConsulting\Comments\Models\Comment;
use RoundlyConsulting\Reports\Events\ReportResolved;
use RoundlyConsulting\Reports\Events\ReportThresholdReached;

/**
 * Bridges reports' moderation decisions to a comment's visibility lifecycle.
 *
 * When a report against a comment is upheld (`ReportResolved`) or a comment
 * crosses the open-report threshold (`ReportThresholdReached`), the comment is
 * auto-hidden (status → Hidden, re-emitting `CommentHidden`). Because reports is
 * polymorphic and shared, every subject is guarded against the configured comment
 * model before acting; non-comment subjects are ignored.
 *
 * All behaviour is config-gated by `comments.moderation`; the transition is
 * idempotent — a comment that is not currently Approved is left untouched.
 */
final class SyncCommentVisibilityFromReports
{
    /**
     * Auto-hide a comment whose report was upheld, per
     * `comments.moderation.on_resolved` ('hide' | null to disable).
     */
    public function handleResolved(ReportResolved $event): void
    {
        $action = config('comments.moderation.on_resolved', 'hide');

        if ($action !== 'hide') {
            return;
        }

        $this->hide($event->report->reported);
    }

    /**
     * Auto-hide a comment that crossed the global `reports.threshold`, when
     * `comments.moderation.auto_hide` is enabled.
     */
    public function handleThresholdReached(ReportThresholdReached $event): void
    {
        if (! (bool) config('comments.moderation.auto_hide', true)) {
            return;
        }

        $this->hide($event->subject);
    }

    private function hide(?Model $subject): void
    {
        $comment = $this->resolveComment($subject);

        // Only act on a currently-approved (publicly visible) comment so the
        // transition stays idempotent (never re-hides an already-hidden comment).
        if (! $comment instanceof Comment || $comment->status !== CommentStatus::Approved) {
            return;
        }

        $comment->update(['status' => CommentStatus::Hidden]);

        CommentHidden::dispatch($comment);
    }

    private function resolveComment(?Model $subject): ?Comment
    {
        if (! $subject instanceof Model) {
            return null;
        }

        $configured = config('comments.model', Comment::class);
        $model = is_string($configured) ? $configured : Comment::class;

        if (! $subject instanceof $model) {
            return null;
        }

        return $subject instanceof Comment ? $subject : null;
    }
}
