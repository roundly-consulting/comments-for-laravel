<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\Concerns;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Likes\Traits\HasLikes;

/**
 * First-class likes/reactions for the bundled Comment model, built on
 * roundly-consulting/likes-for-laravel.
 *
 * Wires the comment into likes' `HasLikes` seam so a comment can be liked,
 * counted, ranked (most-liked / trending) and hydrated with per-viewer state in
 * a single query, and adds a compact `likeState()` payload for API/Blade
 * rendering. This replaces the package's former native emoji reaction system.
 *
 * Actors are always passed explicitly (e.g. `Likes::actor($user)->like($comment)`);
 * this package never resolves the acting user implicitly from the auth guard.
 *
 * @mixin Model
 */
trait HasCommentReactions
{
    use HasLikes;

    /**
     * Compact like payload for API responses / Blade: the total count, the
     * viewer's state, and the per-reaction breakdown. Pass the viewer explicitly;
     * a null viewer renders a guest state (`liked = false`, `reaction = null`).
     *
     * @return array{count: int, viewer_state: array{liked: bool, reaction: ?string}, breakdown: array<string, int>}
     */
    public function likeState(?Model $viewer = null): array
    {
        return $this->toLikeArray($viewer);
    }
}
