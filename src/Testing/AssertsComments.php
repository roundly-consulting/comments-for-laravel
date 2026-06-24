<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\Testing;

use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Assert;
use RoundlyConsulting\Comments\Models\Comment;

/**
 * Assertion helpers for host-app tests. Use this trait in a test case to make
 * comment expectations read clearly.
 */
trait AssertsComments
{
    protected function assertCommented(Model $subject, ?string $body = null): void
    {
        $query = Comment::query()
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
        $exists = Comment::query()
            ->where('commentable_type', $subject->getMorphClass())
            ->where('commentable_id', $subject->getKey())
            ->exists();

        Assert::assertFalse($exists, 'Failed asserting that the subject has no comments.');
    }

    protected function assertCommentCount(Model $subject, int $expected): void
    {
        $actual = Comment::query()
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
