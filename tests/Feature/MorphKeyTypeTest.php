<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use RoundlyConsulting\Testing\Database\DriverMatrix;

/**
 * The `comments.key_type` seam, proven on the only engine that can answer it.
 *
 * Comments points at four polymorphic targets — actor, commentable, mentionable and
 * lockable. Before this seam their id columns were hardcoded `morphs()`/`nullableMorphs()`
 * (unsigned bigint), so a host whose commenter or subject is uuid/ulid keyed could not
 * relate to them on a strict engine. The seam makes the id type config-driven while keeping
 * the shipped bigint schema byte-identical.
 */
if (! function_exists('morphKtColumn')) {
    /**
     * @return array{type: string, type_name: string, nullable: bool|null}
     */
    function morphKtColumn(string $table, string $column): array
    {
        foreach (Schema::getColumns($table) as $c) {
            if ($c['name'] === $column) {
                return ['type' => $c['type'], 'type_name' => $c['type_name'], 'nullable' => $c['nullable']];
            }
        }

        return ['type' => 'MISSING', 'type_name' => 'MISSING', 'nullable' => null];
    }
}

$pgsqlOnly = fn (): bool => DriverMatrix::driver() !== 'pgsql';

it('emits byte-identical morph columns on the bigint default', function (): void {
    // The raw baseline: `morphs()` for a required target, `nullableMorphs()` for an
    // optional one. `morphKey(BigInt)` must produce exactly this — the whole safety
    // property of the sweep is that a default host sees no schema change.
    Schema::dropIfExists('kt_ref');
    Schema::create('kt_ref', function (Blueprint $t): void {
        $t->id();
        $t->morphs('req');
        $t->nullableMorphs('opt');
    });

    $req = morphKtColumn('kt_ref', 'req_id');
    $reqType = morphKtColumn('kt_ref', 'req_type');
    $opt = morphKtColumn('kt_ref', 'opt_id');
    $optType = morphKtColumn('kt_ref', 'opt_type');

    expect(morphKtColumn('comments', 'commentable_id'))->toBe($req)
        ->and(morphKtColumn('comments', 'commentable_type'))->toBe($reqType)
        ->and(morphKtColumn('comment_locks', 'lockable_id'))->toBe($req)
        ->and(morphKtColumn('comment_locks', 'lockable_type'))->toBe($reqType)
        ->and(morphKtColumn('comments', 'actor_id'))->toBe($opt)
        ->and(morphKtColumn('comments', 'actor_type'))->toBe($optType)
        ->and(morphKtColumn('comment_mentions', 'mentionable_id'))->toBe($opt)
        ->and(morphKtColumn('comment_mentions', 'mentionable_type'))->toBe($optType);

    Schema::dropIfExists('kt_ref');
});

it('renders each configured key type as a distinct real column type', function (string $keyType, string $expected): void {
    config()->set('comments.key_type', $keyType);

    Schema::dropIfExists('comment_locks');
    Schema::dropIfExists('comment_mentions');
    Schema::dropIfExists('comments');

    $migration = require __DIR__.'/../../database/migrations/0001_01_01_000000_create_comments_table.php';
    $migration->up();

    // Every polymorphic id column follows the configured type, not just the first.
    expect(morphKtColumn('comments', 'commentable_id')['type'])->toBe($expected)
        ->and(morphKtColumn('comments', 'actor_id')['type'])->toBe($expected)
        ->and(morphKtColumn('comment_mentions', 'mentionable_id')['type'])->toBe($expected)
        ->and(morphKtColumn('comment_locks', 'lockable_id')['type'])->toBe($expected)
        // The morph *type* column is a class-name string on every key type.
        ->and(morphKtColumn('comments', 'commentable_type')['type'])->toBe('character varying(255)');
})->with([
    'bigint' => ['bigint', 'bigint'],
    'uuid' => ['uuid', 'uuid'],
    'ulid' => ['ulid', 'character(26)'],
])->skip($pgsqlOnly, 'needs the postgres catalog to tell the key types apart — sqlite affinity hides it');

it('falls back to the bigint schema for an unrecognized key type', function (): void {
    config()->set('comments.key_type', 'nonsense');

    Schema::dropIfExists('comment_locks');
    Schema::dropIfExists('comment_mentions');
    Schema::dropIfExists('comments');

    $migration = require __DIR__.'/../../database/migrations/0001_01_01_000000_create_comments_table.php';
    $migration->up();

    $expected = DriverMatrix::driver() === 'pgsql' ? 'bigint' : 'integer';

    expect(morphKtColumn('comments', 'commentable_id')['type'])->toBe($expected)
        ->and(morphKtColumn('comments', 'actor_id')['type'])->toBe($expected);
});
