<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\Actions;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use RoundlyConsulting\Comments\Models\Comment;
use RoundlyConsulting\Comments\Models\CommentMention;

/**
 * Keeps a comment's stored `@handle` mentions in step with its body. A building block of the
 * write and edit actions — not a host-facing operation of its own.
 *
 * @internal
 */
final readonly class SyncCommentMentionsAction
{
    public function __construct(
        private NotifyCommentMentionsAction $notify,
    ) {}

    /**
     * Parse "@handle" tokens from the comment body and sync them against the stored
     * mentions: rows for handles still present are kept as they are, rows for dropped
     * handles are deleted, and new handles are stored and resolved via the configured
     * resolver. Notifying is {@see NotifyCommentMentionsAction}'s: CommentMentioned fires
     * once per person, and only while the comment is approved — so editing a comment never
     * re-notifies the people it already notified (even under a different handle).
     */
    public function execute(Comment $comment): void
    {
        /** @var Collection<int, CommentMention> $existing */
        $existing = $comment->mentions()->get();

        // Read before the dropped rows go: a person re-mentioned under another handle keeps
        // the notification they already had.
        $notified = $existing
            ->filter(fn (CommentMention $mention): bool => $mention->notified_at !== null)
            ->map(fn (CommentMention $mention): ?string => $mention->mentionedIdentity())
            ->filter()
            ->all();

        $handles = $this->parse($comment->comment);

        $comment->mentions()
            ->whereKey($existing->reject(fn (CommentMention $mention): bool => in_array($mention->handle, $handles, true))->modelKeys())
            ->delete();

        $stored = $existing->pluck('handle')->all();

        foreach ($handles as $handle) {
            if (in_array($handle, $stored, true)) {
                continue;
            }

            $mentionable = $this->resolve($handle);

            $mention = $comment->mentions()->make([
                'handle' => $handle,
                'mentionable_id' => $mentionable?->getKey(),
                'mentionable_type' => $mentionable?->getMorphClass(),
            ]);

            $identity = $mention->mentionedIdentity();

            if ($identity !== null && in_array($identity, $notified, true)) {
                $mention->notified_at = Carbon::now();
            }

            $mention->save();
        }

        $this->notify->execute($comment);
    }

    /**
     * The unique `@handle`s in the body. A handle may carry `.` and `-` inside it
     * (`@john.doe`) but ends on a letter, digit or underscore, so the full stop of
     * "thanks @alice." is not part of the handle; `(?<!\w)` keeps emails out.
     *
     * @return list<string>
     */
    private function parse(string $body): array
    {
        preg_match_all('/(?<!\w)@([A-Za-z0-9_](?:[A-Za-z0-9_.-]*[A-Za-z0-9_])?)/', $body, $matches);

        /** @var list<string> $handles */
        $handles = array_values(array_unique($matches[1]));

        return $handles;
    }

    private function resolve(string $handle): ?Model
    {
        /** @var callable|array{0: class-string, 1: string}|null $resolver */
        $resolver = config('comments.mention_resolver');

        if ($resolver === null) {
            return null;
        }

        $resolved = is_callable($resolver) ? $resolver($handle) : null;

        return $resolved instanceof Model ? $resolved : null;
    }
}
