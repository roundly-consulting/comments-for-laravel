<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments;

use Closure;
use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Comments\Listeners\SyncCommentVisibilityFromReports;
use RoundlyConsulting\Comments\Support\CommentModel;
use RoundlyConsulting\Comments\Support\CommentsConfig;
use RoundlyConsulting\PackageToolkit\Concerns\RegistersBlueprintMacros;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;
use RoundlyConsulting\PackageToolkit\Package;
use RoundlyConsulting\PackageToolkit\PackageServiceProvider;
use RoundlyConsulting\PackageToolkit\Support\Config;
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
                'Require approval' => Config::boolean('comments.require_approval') ? 'ON' : 'OFF',
                'Max length' => self::orInvalid(static fn (): string => CommentsConfig::maxLength().' chars'),
                'Max depth' => self::orInvalid(static fn (): string => (string) CommentsConfig::maxDepth()),
                // A count, never the terms — a blocklist is moderation-sensitive.
                'Blocklist' => self::orInvalid(static function (): string {
                    $size = count(CommentsConfig::blocklist());

                    return $size === 0 ? 'NONE' : $size.' term(s)';
                }),
                'Authorization' => Config::boolean('comments.authorization') ? 'ON' : 'OFF',
                'Auto-moderation' => Config::boolean('comments.moderation.auto_hide', true) ? 'ON' : 'OFF',
                'Inline media' => Config::boolean('comments.media.inline.enabled', true) ? 'ON' : 'OFF',
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

    /**
     * A strict read rendered for `about`, or `INVALID` when the setting is broken — so
     * `php artisan about` still works on a misconfigured host while every real read throws.
     *
     * @param  Closure(): string  $read
     */
    private static function orInvalid(Closure $read): string
    {
        try {
            return $read();
        } catch (InvalidConfigurationException) {
            return 'INVALID';
        }
    }
}
