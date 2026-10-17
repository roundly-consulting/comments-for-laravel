<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\Testing;

use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Assert;
use RoundlyConsulting\Comments\Support\CommentModel;

/**
 * Assertion helpers for host-app tests. Use this trait in a test case to make
 * comment expectations read clearly.
 *
 * Every helper reads through the `comments.model` seam, so it sees exactly what the host's
 * configured model sees (its table, connection and global scopes).
 */
trait AssertsComments
{
    protected function assertCommented(Model $subject, ?string $body = null): void
    {
        $query = CommentModel::class()::query()
            ->where('commentable_type', $subject->getMorphClass())
            ->where('commentable_id', $subject->getKey());

        if ($body !== null) {
            $query->where('comment', $body);
        }

        Assert::assertTrue(
            $query->exists(),
            'Failed asserting that the subject has a comment'.($body !== null ? " with body [{$body}]" : '').'.',
        );
    }

    protected function assertNotCommented(Model $subject): void
    {
        $exists = CommentModel::class()::query()
            ->where('commentable_type', $subject->getMorphClass())
            ->where('commentable_id', $subject->getKey())
            ->exists();

        Assert::assertFalse($exists, 'Failed asserting that the subject has no comments.');
    }

    protected function assertCommentCount(Model $subject, int $expected): void
    {
        $actual = CommentModel::class()::query()
            ->where('commentable_type', $subject->getMorphClass())
            ->where('commentable_id', $subject->getKey())
            ->count();

        Assert::assertSame(
            $expected,
            $actual,
            "Failed asserting that the subject has {$expected} comment(s); found {$actual}.",
        );
    }
}
