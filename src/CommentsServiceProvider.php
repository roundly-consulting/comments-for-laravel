<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use RoundlyConsulting\Comments\Listeners\SyncCommentVisibilityFromReports;
use RoundlyConsulting\Reports\Events\ReportResolved;
use RoundlyConsulting\Reports\Events\ReportThresholdReached;

final class CommentsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Merge so the host app only needs to publish/override what it wants.
        $this->mergeConfigFrom(__DIR__.'/../config/comments.php', 'comments');

        $this->app->singleton(CommentManager::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'comments');

        // Auto-hide a comment when a report against it is upheld or it crosses the
        // global reports threshold (config-gated by comments.moderation).
        Event::listen(ReportResolved::class, [SyncCommentVisibilityFromReports::class, 'handleResolved']);
        Event::listen(ReportThresholdReached::class, [SyncCommentVisibilityFromReports::class, 'handleThresholdReached']);

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/comments.php' => config_path('comments.php'),
            ], 'comments-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'comments-migrations');

            $this->publishes([
                __DIR__.'/../resources/lang' => $this->app->langPath('vendor/comments'),
            ], 'comments-translations');
        }
    }
}
