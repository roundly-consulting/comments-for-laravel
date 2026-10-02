<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use RoundlyConsulting\PackageToolkit\Enums\KeyType;

return new class extends Migration
{
    public function up(): void
    {
        $keyType = KeyType::fromConfig('comments.key_type');

        Schema::create('comments', function (Blueprint $table) use ($keyType): void {
            $table->id();
            $table->boolean('visible')->default(true);
            $table->string('status')->default('approved')->index();
            $table->foreignId('parent_id')->nullable()->index();
            $table->morphKey('actor', $keyType, nullable: true);
            $table->morphKey('commentable', $keyType, nullable: false);
            $table->text('comment');
            $table->timestamp('locked_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['commentable_type', 'commentable_id', 'status']);
        });

        Schema::create('comment_mentions', function (Blueprint $table) use ($keyType): void {
            $table->id();
            $table->foreignId('comment_id')->index();
            $table->string('handle');
            $table->morphKey('mentionable', $keyType, nullable: true);
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();

            $table->index(['comment_id', 'handle']);
        });

        Schema::create('comment_locks', function (Blueprint $table) use ($keyType): void {
            $table->id();
            $table->morphKey('lockable', $keyType, nullable: false);
            $table->timestamps();

            $table->unique(['lockable_type', 'lockable_id']);
        });
    }
};
