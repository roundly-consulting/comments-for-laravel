<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments;

use Illuminate\Support\ServiceProvider;

final class CommentsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Merge so the host app only needs to publish/override what it wants.
        $this->mergeConfigFrom(__DIR__.'/../config/comments.php', 'comments');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/comments.php' => config_path('comments.php'),
            ], 'comments-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'comments-migrations');
        }
    }
}
