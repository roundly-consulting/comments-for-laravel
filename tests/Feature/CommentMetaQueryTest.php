<?php

declare(strict_types=1);

use RoundlyConsulting\Comments\Exceptions\InvalidCommentMetaPathException;
use RoundlyConsulting\Comments\Facades\Comments;
use RoundlyConsulting\Comments\Models\Comment;
use RoundlyConsulting\Comments\Tests\PostTestModel;
use RoundlyConsulting\PackageToolkit\Enums\DatabaseDriver;
use RoundlyConsulting\Testing\Database\DriverMatrix;

/**
 * C-3 — `whereMeta()` / `whereMetaMissing()` on the fluent query.
 *
 * Json path SQL is where the drivers disagree (`json_extract` on SQLite, `->>` / `::jsonb` on
 * Postgres, `json_unquote(json_extract())` on MySQL), so this file runs unchanged on every leg
 * of the driver matrix: in-memory SQLite locally and on the coverage matrix, and the real
 * engines on the `test-pgsql` / `test-mysql` legs. The first case pins that the leg really is
 * the engine it claims to be, so a leg that silently stayed on SQLite cannot pass for one.
 */
beforeEach(function (): void {
    $this->post = PostTestModel::create();
    $other = PostTestModel::create();

    $make = fn (string $body, ?array $meta, ?PostTestModel $on = null): Comment => Comment::factory()
        ->on($on ?? $this->post)
        ->state(['comment' => $body, 'meta' => $meta])
        ->create();

    $make('agent', ['tickets' => ['internal' => true, 'role' => 'agent', 'channel' => 'email', 'n' => 2]]);
    $make('requester', ['tickets' => ['internal' => false, 'role' => 'requester', 'channel' => 'web', 'n' => 3]]);
    $make('imported', ['source' => 'import']);
    $make('plain', null);
    $make('null role', ['tickets' => ['role' => null]]);
    $make('other subject', ['tickets' => ['internal' => true, 'role' => 'agent']], $other);
});

if (! function_exists('metaBodies')) {
    /**
     * The bodies of the test post's comments the refinement keeps, sorted.
     *
     * @return list<string>
     */
    function metaBodies(callable $refine): array
    {
        $query = Comments::for(test()->post);
        $refine($query);

        return $query->get()->pluck('comment')->sort()->values()->all();
    }
}

it('runs on the driver the leg declares', function (): void {
    expect(DatabaseDriver::current())->toBe(DatabaseDriver::from(DriverMatrix::driver()));
});

it('matches a string at a nested path', function (): void {
    expect(metaBodies(fn ($q) => $q->whereMeta('tickets.role', 'agent')))->toBe(['agent'])
        ->and(metaBodies(fn ($q) => $q->whereMeta('tickets.channel', 'web')))->toBe(['requester'])
        ->and(metaBodies(fn ($q) => $q->whereMeta('source', 'import')))->toBe(['imported']);
});

it('matches json booleans by value', function (): void {
    expect(metaBodies(fn ($q) => $q->whereMeta('tickets.internal', true)))->toBe(['agent'])
        ->and(metaBodies(fn ($q) => $q->whereMeta('tickets.internal', false)))->toBe(['requester']);
});

it('matches json integers', function (): void {
    expect(metaBodies(fn ($q) => $q->whereMeta('tickets.n', 3)))->toBe(['requester'])
        ->and(metaBodies(fn ($q) => $q->whereMeta('tickets.n', 4)))->toBe([]);
});

it('matches nothing for a value no comment holds', function (): void {
    expect(metaBodies(fn ($q) => $q->whereMeta('tickets.role', 'participant')))->toBe([])
        ->and(metaBodies(fn ($q) => $q->whereMeta('nowhere.at_all', 'x')))->toBe([]);
});

it('chains meta filters with each other and the subject scope', function (): void {
    expect(metaBodies(fn ($q) => $q->whereMeta('tickets.internal', false)->whereMeta('tickets.channel', 'web')))
        ->toBe(['requester'])
        ->and(metaBodies(fn ($q) => $q->whereMeta('tickets.internal', true)->whereMeta('tickets.channel', 'web')))
        ->toBe([])
        // The other subject's matching comment stays out: for() still scopes.
        ->and(Comments::query()->whereMeta('tickets.role', 'agent')->count())->toBe(2);
});

it('finds comments whose path is missing', function (): void {
    expect(metaBodies(fn ($q) => $q->whereMetaMissing('tickets')))->toBe(['imported', 'plain'])
        ->and(metaBodies(fn ($q) => $q->whereMetaMissing('source')))
        ->toBe(['agent', 'null role', 'plain', 'requester']);
});

it('treats a key holding json null as present, not missing', function (): void {
    expect(metaBodies(fn ($q) => $q->whereMetaMissing('tickets.role')))->toBe(['imported', 'plain'])
        ->and(metaBodies(fn ($q) => $q->whereMetaMissing('tickets.internal')))->toBe(['imported', 'null role', 'plain']);
});

it('moderates in bulk over a meta filter', function (): void {
    $hidden = Comments::for($this->post)->whereMeta('tickets.internal', true)->hideAll();

    expect($hidden)->toBe(1)
        ->and(Comments::for($this->post)->hidden()->get()->pluck('comment')->all())->toBe(['agent']);
});

it('refuses a path that is not plain dot notation', function (string $path): void {
    expect(fn () => Comments::query()->whereMeta($path, 'x'))
        ->toThrow(InvalidCommentMetaPathException::class)
        ->and(fn () => Comments::query()->whereMetaMissing($path))
        ->toThrow(InvalidCommentMetaPathException::class);
})->with([
    'empty' => '',
    'upper case' => 'Tickets.role',
    'empty segment' => 'tickets..role',
    'leading dot' => '.tickets',
    'trailing dot' => 'tickets.',
    'arrow syntax' => 'tickets->role',
    'quote injection' => "tickets'); drop table comments; --",
    'json path syntax' => '$.tickets',
    'wildcard' => 'tickets.*',
    'array index' => 'tickets[0]',
    'space' => 'tickets role',
    // `$` alone would let a trailing newline through.
    'trailing newline' => "tickets\n",
    // Postgres reads a digits-only segment as an array index, MySQL and SQLite as a key.
    'digits-only segment' => 'tickets.0',
    'digits-only root' => '42',
]);

it('names the refused path in the message', function (): void {
    expect(fn () => Comments::query()->whereMeta('Bad.Path', 'x'))
        ->toThrow(InvalidCommentMetaPathException::class, '[Bad.Path]');
});

it('accepts letters, digits and underscores in a segment', function (): void {
    Comment::factory()->on($this->post)->meta(['a1' => ['b_2' => 'yes', 'v2' => 'mixed']])->create(['comment' => 'odd keys']);

    expect(metaBodies(fn ($q) => $q->whereMeta('a1.b_2', 'yes')))->toBe(['odd keys'])
        ->and(metaBodies(fn ($q) => $q->whereMeta('a1.v2', 'mixed')))->toBe(['odd keys']);
});
