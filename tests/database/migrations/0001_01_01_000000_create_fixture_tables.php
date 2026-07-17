<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The host-owned tables the comments fixtures live in. These used to be built by hand in
 * TestCase::defineDatabaseMigrations(); they are a migration now so PackageTestCase can own
 * the whole schema through `migrationSources()` — and so the real-engine reset
 * (drop-all-tables + re-migrate) restores them too, which an inline Schema::create() would
 * not survive on Postgres.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('actors', function (Blueprint $table): void {
            $table->increments('id');
        });

        Schema::create('posts', function (Blueprint $table): void {
            $table->increments('id');
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->increments('id');
        });
    }
};
