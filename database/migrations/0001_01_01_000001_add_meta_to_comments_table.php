<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 1.1.0 — free-form metadata on a comment (`Comments::on($post)->meta([...])`). Additive: the
 * shipped `create_comments_table` stays frozen, so hosts that migrated 1.0 only add a column.
 * `jsonb` is `jsonb` on Postgres (indexable, compared by value) and `json` on MySQL; SQLite
 * keeps it as text. Nullable, because every comment written before 1.1.0 has none.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comments', function (Blueprint $table): void {
            $table->jsonb('meta')->nullable();
        });
    }
};
