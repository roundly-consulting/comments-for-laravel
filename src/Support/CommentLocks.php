<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\Support;

use RoundlyConsulting\Comments\Models\Comment;
use RoundlyConsulting\Comments\Models\CommentLock;

/**
 * The two lock rules, in one place: the write and edit guards and the manager's `isLocked()` /
 * `isThreadLocked()` reads all ask here, so what blocks a write and what a UI is told can never
 * disagree.
 *
 * @internal
 */
final class CommentLocks
{
    /**
     * Whether a subject is locked against new or edited comments.
     */
    public function subjectLocked(string $type, mixed $key): bool
    {
        return CommentLock::query()
            ->where('lockable_type', $type)
            ->where('lockable_id', $key)
            ->exists();
    }

    /**
     * Whether a comment sits in a locked thread: it, or any ancestor, carries a thread lock.
     *
     * A thread lock covers the whole subtree, not just direct replies — a reply to a reply is
     * still in the thread. The walk reads the parent relation, which the reply-depth guard has
     * usually loaded already, and stops at a missing (e.g. soft-deleted) parent or a cycle.
     */
    public function threadLocked(Comment $comment): bool
    {
        $seen = [];
        $current = $comment;

        while (! $current->isLocked()) {
            $seen[] = $current->getKey();

            if ($current->parent_id === null || in_array($current->parent_id, $seen, true)) {
                return false;
            }

            $parent = $current->getRelationValue('parent');

            if (! $parent instanceof Comment) {
                return false;
            }

            $current = $parent;
        }

        return true;
    }
}
