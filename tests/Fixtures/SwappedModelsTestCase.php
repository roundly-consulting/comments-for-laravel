<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\Tests\Fixtures;

use RoundlyConsulting\Comments\Tests\CustomCommentTestModel;
use RoundlyConsulting\Comments\Tests\TestCase;

/**
 * The base case for `tests/Configured` — the suite booted as a host that has swapped
 * `comments.model` in its own `config/comments.php`.
 *
 * Boot order is the whole point, and it is why this is a separate base case (and therefore
 * a separate directory — Pest binds a test case per DIRECTORY, not per file). The superseded
 * `Feature/ConfigSwapTest` set `comments.model` inside the test body. That reads back
 * correctly and proves almost nothing: by the time the body runs, CommentsServiceProvider
 * has already hung its observers and listeners on the packaged `Comment` class, so a swap
 * test written that way is structurally incapable of catching the boot-time bug it is named
 * for. A real host sets the key before boot; so does this.
 *
 * Note the `array_merge(parent::configBeforeBoot(), …)`: dropping it would silently discard
 * the base's own media-library wiring, with no error and no red — the same decapitation an
 * un-parented `defineEnvironment()` override causes one level up.
 *
 * @see TestCase
 */
abstract class SwappedModelsTestCase extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    protected function configBeforeBoot(): array
    {
        return array_merge(parent::configBeforeBoot(), [
            'comments.model' => CustomCommentTestModel::class,
        ]);
    }
}
