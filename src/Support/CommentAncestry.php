<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\Support;

use RoundlyConsulting\Comments\Models\Comment;

/**
 * Walks a comment's ancestors for the thread-lock and reply-depth rules.
 *
 * A soft-deleted ancestor still counts: deleting a comment inside a locked thread must not
 * unlock everything below it, nor let a reply chain grow past `comments.max_depth`. So the walk
 * reads trashed parents too — the `parent` relation's SoftDeletes scope would end it at the
 * first deleted comment. Only a parent that no longer exists at all (force-deleted) ends it.
 *
 * @internal
 */
final class CommentAncestry
{
    /**
     * The comment's parent, trashed or not; null for a root or a force-deleted parent. An
     * already-loaded parent is reused; otherwise it is read without caching it on the relation,
     * so `$comment->parent` keeps its usual (trashed-excluded) answer for the caller.
     */
    public function parentOf(Comment $comment): ?Comment
    {
        if ($comment->parent_id === null) {
            return null;
        }

        $loaded = $comment->relationLoaded('parent') ? $comment->getRelation('parent') : null;

        if ($loaded instanceof Comment) {
            return $loaded;
        }

        $parent = CommentModel::class()::query()
            ->withTrashed()
            ->whereKey($comment->parent_id)
            ->first();

        return $parent instanceof Comment ? $parent : null;
    }

    /**
     * How deep the comment sits in its thread: a root is 1, its reply 2, and so on. Every
     * `parent_id` link counts, even one whose parent is gone; a cycle in corrupt data ends the
     * walk instead of looping.
     */
    public function depthOf(Comment $comment): int
    {
        $depth = 1;
        $seen = [$comment->getKey()];
        $current = $comment;

        while ($current->parent_id !== null) {
            $depth++;
            $parent = $this->parentOf($current);

            if (! $parent instanceof Comment || in_array($parent->getKey(), $seen, true)) {
                break;
            }

            $seen[] = $parent->getKey();
            $current = $parent;
        }

        return $depth;
    }
}
