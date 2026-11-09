<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Comments\Contracts\MentionableGroup;

/**
 * A group a mention resolver can return (`@design-team`): a plain object over a member list,
 * which may repeat a member — the expansion must store each one once.
 */
final readonly class MentionGroupFixture implements MentionableGroup
{
    /**
     * @param  list<Model>  $members
     */
    public function __construct(private array $members) {}

    public function mentionableMembers(): iterable
    {
        yield from $this->members;
    }
}
