<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\Tests;

use RoundlyConsulting\Comments\Models\Comment;
use RoundlyConsulting\Testing\Fixtures\Concerns\CountsCreations;

/**
 * The host subclass the `comments.model` seam invites.
 *
 * `CountsCreations` is required by `toHonourModelSwap`, not optional, and it is the whole
 * proof: `instanceof` passes even when a package helper created the row as the PACKAGED
 * class (permissions #31), because re-querying through the host class re-hydrates the row
 * whatever it was created as. Counting `created` events on this exact class is the
 * independent oracle. Without the trait the assertion used to silently drop that half —
 * alerts' seam-bypass proof stayed green until it was added.
 */
final class CustomCommentTestModel extends Comment
{
    use CountsCreations;

    public $table = 'comments';
}
