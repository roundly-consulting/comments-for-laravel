<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\Tests;

/**
 * A config:cache-safe `comments.mention_resolver`: configured as the class-string (invokable)
 * or as `[MentionResolverTestFixture::class, 'resolve']`, never as a closure. Resolved through
 * the container, so it may take constructor dependencies.
 */
final class MentionResolverTestFixture
{
    public function __invoke(string $handle): ?ActorTestModel
    {
        return $this->resolve($handle);
    }

    public function resolve(string $handle): ?ActorTestModel
    {
        // `@user7` resolves to actor 7.
        return str_starts_with($handle, 'user')
            ? ActorTestModel::query()->find((int) substr($handle, 4))
            : null;
    }
}
