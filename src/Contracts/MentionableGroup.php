<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * A group a mention can name: return it from `comments.mention_resolver` and the `@handle`
 * expands to its members. Each member is stored once per handle, the comment's own author is
 * left out, and a person reached through several handles is still notified once.
 *
 * Implement it on an Eloquent model (a team) or on any plain object (`@here`). A group with no
 * members left (empty, or only the author) stores the raw handle, like an unresolved mention.
 */
interface MentionableGroup
{
    /**
     * @return iterable<Model>
     */
    public function mentionableMembers(): iterable;
}
