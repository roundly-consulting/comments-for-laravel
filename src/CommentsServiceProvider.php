<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments;

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Comments\Listeners\SyncCommentVisibilityFromReports;
use RoundlyConsulting\Comments\Support\CommentModel;
use RoundlyConsulting\PackageToolkit\Concerns\RegistersBlueprintMacros;
use RoundlyConsulting\PackageToolkit\Package;
use RoundlyConsulting\PackageToolkit\PackageServiceProvider;
use RoundlyConsulting\Reports\Events\ReportResolved;
use RoundlyConsulting\Reports\Events\ReportThresholdReached;

final class CommentsServiceProvider extends PackageServiceProvider
{
    use RegistersBlueprintMacros;

    public function configurePackage(Package $package): void
    {
        $package
            ->name('comments')
            ->hasConfigFile()
            ->hasMigrations()
            ->hasTranslations()
            ->contributesToAbout(static fn (): array => [
                'Model' => class_basename(CommentModel::class()),
                'Require approval' => config('comments.require_approval') === true ? 'ON' : 'OFF',
                'Max length' => ((int) config('comments.max_length', 5000)).' chars',
                'Max depth' => (string) (int) config('comments.max_depth', 5),
                // A count, never the terms — a blocklist is moderation-sensitive.
                'Blocklist' => self::blocklistSize() === 0 ? 'NONE' : self::blocklistSize().' term(s)',
                'Authorization' => config('comments.authorization') === true ? 'ON' : 'OFF',
                'Auto-moderation' => config('comments.moderation.auto_hide') === true ? 'ON' : 'OFF',
                'Inline media' => config('comments.media.inline.enabled') === true ? 'ON' : 'OFF',
            ]);
    }

    public function register(): void
    {
        parent::register();

        $this->app->singleton(CommentsManager::class);
    }

    public function boot(): void
    {
        parent::boot();

        // The comments migrations key their polymorphic columns through the toolkit's
        // `morphKey` macro, so it must exist before they run. Registration is idempotent —
        // the toolkit guards it with `hasMacro()`.
        $this->registerBlueprintMacros();

        // Auto-hide a comment when a report against it is upheld or it crosses the
        // global reports threshold (config-gated by comments.moderation).
        Event::listen(ReportResolved::class, [SyncCommentVisibilityFromReports::class, 'handleResolved']);
        Event::listen(ReportThresholdReached::class, [SyncCommentVisibilityFromReports::class, 'handleThresholdReached']);
    }

    private static function blocklistSize(): int
    {
        $blocklist = config('comments.blocklist', []);

        return is_array($blocklist) ? count($blocklist) : 0;
    }
}
