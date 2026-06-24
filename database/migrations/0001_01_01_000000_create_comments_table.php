<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comments', function (Blueprint $table): void {
            $table->id();
            $table->boolean('visible')->default(true);
            $table->string('status')->default('approved')->index();
            $table->foreignId('parent_id')->nullable()->index();
            $table->nullableMorphs('actor');
            $table->morphs('commentable');
            $table->text('comment');
            $table->timestamp('locked_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['commentable_type', 'commentable_id', 'status']);
        });

        Schema::create('comment_reactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('comment_id')->index();
            $table->nullableMorphs('reactor');
            $table->string('reaction');
            $table->timestamps();

            $table->unique(['comment_id', 'reactor_type', 'reactor_id', 'reaction'], 'comment_reactions_unique');
        });

        Schema::create('comment_mentions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('comment_id')->index();
            $table->string('handle');
            $table->nullableMorphs('mentionable');
            $table->timestamps();

            $table->index(['comment_id', 'handle']);
        });

        Schema::create('comment_locks', function (Blueprint $table): void {
            $table->id();
            $table->morphs('lockable');
            $table->timestamps();

            $table->unique(['lockable_type', 'lockable_id']);
        });
    }
};
