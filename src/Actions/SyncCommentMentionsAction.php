<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\Actions;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Comments\Events\CommentMentioned;
use RoundlyConsulting\Comments\Models\Comment;

final class SyncCommentMentionsAction
{
    /**
     * Parse "@handle" tokens from the comment body, (re)store them, resolve
     * each via the configured resolver, and dispatch CommentMentioned for any
     * that resolve to a model.
     */
    public function execute(Comment $comment): void
    {
        $comment->mentions()->delete();

        foreach ($this->parse($comment->comment) as $handle) {
            $mentionable = $this->resolve($handle);

            $mention = $comment->mentions()->create([
                'handle' => $handle,
                'mentionable_id' => $mentionable?->getKey(),
                'mentionable_type' => $mentionable?->getMorphClass(),
            ]);

            if ($mentionable instanceof Model) {
                CommentMentioned::dispatch($mention);
            }
        }
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
