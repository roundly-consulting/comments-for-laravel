<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\Actions;

use Illuminate\Support\Carbon;
use RoundlyConsulting\Comments\Enums\CommentStatus;
use RoundlyConsulting\Comments\Events\CommentMentioned;
use RoundlyConsulting\Comments\Models\Comment;
use RoundlyConsulting\Comments\Models\CommentMention;

/**
 * Fires `CommentMentioned` for the people an approved comment mentions and has not notified
 * yet. A building block of the write, edit and approve actions — not a host-facing operation.
 *
 * A comment the moderation layer holds back (blocklist `pending`/`hidden`, `require_approval`,
 * a moderator's hide) notifies nobody: its resolved mentions wait, and fire once it is approved.
 * Each person is notified once per comment — `notified_at` is claimed with a conditional UPDATE,
 * so a repeat (or concurrent) approval, a hide/approve round trip or an edit never re-notifies,
 * even when the same person is mentioned again under another handle.
 *
 * @internal
 */
final readonly class NotifyCommentMentionsAction
{
    public function execute(Comment $comment): void
    {
        if ($comment->status !== CommentStatus::Approved) {
            return;
        }

        $mentions = $comment->mentions()
            ->whereNotNull('mentionable_id')
            ->orderBy('id')
            ->get();

        $notified = $mentions
            ->filter(fn (CommentMention $mention): bool => $mention->notified_at !== null)
            ->map(fn (CommentMention $mention): ?string => $mention->mentionedIdentity())
            ->all();

        foreach ($mentions as $mention) {
            if ($mention->notified_at !== null) {
                continue;
            }

            $identity = $mention->mentionedIdentity();
            $alreadyNotified = in_array($identity, $notified, true);
            $notified[] = $identity;

            $claimed = $this->claim($mention);

            if ($claimed && ! $alreadyNotified) {
                CommentMentioned::dispatch($mention);
            }
        }
    }

    /**
     * Stamp the mention as notified, only if no one else has: true when this call won it.
     */
    private function claim(CommentMention $mention): bool
    {
        $now = Carbon::now();

        $claimed = CommentMention::query()
            ->whereKey($mention->getKey())
            ->whereNull('notified_at')
            ->update(['notified_at' => $now]) === 1;

        if ($claimed) {
            $mention->notified_at = $now;
            $mention->syncOriginalAttribute('notified_at');
        }

        return $claimed;
    }
}
