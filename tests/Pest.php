<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use RoundlyConsulting\Comments\Tests\Fixtures\SwappedModelsTestCase;
use RoundlyConsulting\Comments\Tests\TestCase;

// Explicit paths, not `->in(__DIR__)`: the Configured directory below needs a different
// base case (comments.model swapped BEFORE boot) and a blanket bind would claim it first —
// Pest binds a test case per directory, not per file. ArchTest.php is listed because
// `swappableModelsAreNotFinal` reads the `comments.model` config default and so needs the
// app booted; an arch file is not automatically test-cased.
uses(TestCase::class)->in('ArchTest.php', 'Feature', 'Unit');

// The model-swap proof needs `comments.model` pointed at a host subclass BEFORE the
// providers boot, so it runs on its own base case in its own directory.
uses(SwappedModelsTestCase::class)->in('Configured');

/*
 * Re-migrate the comments tables for `comments.key_type` = uuid/ulid, plus the table of the
 * keyed host model the test writes on.
 */
if (! function_exists('migrateCommentsKeyedBy')) {
    /**
     * @param  class-string<Model>  $model
     */
    function migrateCommentsKeyedBy(string $keyType, string $model): void
    {
        config()->set('comments.key_type', $keyType);

        Schema::dropIfExists('comment_locks');
        Schema::dropIfExists('comment_mentions');
        Schema::dropIfExists('comments');

        (require __DIR__.'/../database/migrations/0001_01_01_000000_create_comments_table.php')->up();

        Schema::create((new $model)->getTable(), function (Blueprint $table) use ($keyType): void {
            $keyType === 'ulid' ? $table->ulid('id')->primary() : $table->uuid('id')->primary();
        });
    }
}
