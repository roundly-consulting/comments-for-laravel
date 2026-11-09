<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use RoundlyConsulting\PackageToolkit\Enums\KeyType;

/**
 * 2.1.0 — edit history (`comments.edit_history`). Additive: a new table, nothing in the shipped
 * tables changes. One row per edit, holding the body the edit replaced, who edited and when.
 * `revision` numbers a comment's edits 1, 2, 3…; the unique index backs up the row lock the
 * update action takes, so two concurrent edits can never claim the same number.
 */
return new class extends Migration
{
    public function up(): void
    {
        $keyType = KeyType::fromConfig('comments.key_type');

        Schema::create('comment_revisions', function (Blueprint $table) use ($keyType): void {
            $table->id();
            $table->foreignId('comment_id')->index();
            $table->unsignedInteger('revision');
            $table->text('body');
            $table->morphKey('editor', $keyType, nullable: true);
            $table->timestamps();

            $table->unique(['comment_id', 'revision']);
        });
    }
};
