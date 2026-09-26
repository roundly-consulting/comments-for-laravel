<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\Actions;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Comments\Events\CommentMentioned;
use RoundlyConsulting\Comments\Models\Comment;
use RoundlyConsulting\Comments\Models\CommentMention;

final class SyncCommentMentionsAction
{
    /**
     * Parse "@handle" tokens from the comment body and sync them against the stored
     * mentions: rows for handles still present are kept as they are, rows for dropped
     * handles are deleted, and new handles are stored and resolved via the configured
     * resolver. CommentMentioned fires only for a new handle that resolves to someone
     * this comment did not already mention — so editing a comment never re-notifies
     * the people it already mentioned (even under a different handle).
     */
    public function execute(Comment $comment): void
    {
        /** @var Collection<int, CommentMention> $existing */
        $existing = $comment->mentions()->get();

        $alreadyMentioned = $existing
            ->filter(fn (CommentMention $mention): bool => $mention->mentionable_id !== null)
            ->map(fn (CommentMention $mention): string => $this->identity($mention->mentionable_type, $mention->mentionable_id))
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

            $mention = $comment->mentions()->create([
                'handle' => $handle,
                'mentionable_id' => $mentionable?->getKey(),
                'mentionable_type' => $mentionable?->getMorphClass(),
            ]);

            if (! $mentionable instanceof Model) {
                continue;
            }

            $identity = $this->identity($mentionable->getMorphClass(), $mentionable->getKey());

            if (! in_array($identity, $alreadyMentioned, true)) {
                $alreadyMentioned[] = $identity;

                CommentMentioned::dispatch($mention);
            }
        }
    }

    private function identity(?string $type, mixed $key): string
    {
        return $type.'|'.(is_scalar($key) ? (string) $key : '');
    }

    /**
     * @return list<string>
     */
    private function parse(string $body): array
    {
        preg_match_all('/(?<!\w)@([A-Za-z0-9_.-]+)/', $body, $matches);

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
