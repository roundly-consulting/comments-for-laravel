<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\Tests;

use Illuminate\Support\ServiceProvider;
use RoundlyConsulting\Approvals\ApprovalsServiceProvider;
use RoundlyConsulting\Comments\CommentsServiceProvider;
use RoundlyConsulting\Likes\LikesServiceProvider;
use RoundlyConsulting\MediaLibrary\MediaLibraryServiceProvider;
use RoundlyConsulting\Reports\ReportsServiceProvider;
use RoundlyConsulting\Testing\PackageTestCase;

abstract class TestCase extends PackageTestCase
{
    /**
     * Every provider comments hard-requires, in registration order. A host auto-discovers
     * these; the suite must list them or the test environment is a fiction.
     *
     * @return list<class-string<ServiceProvider>>
     */
    protected function packageProviders(): array
    {
        return [
            ApprovalsServiceProvider::class,
            LikesServiceProvider::class,
            MediaLibraryServiceProvider::class,
            ReportsServiceProvider::class,
            CommentsServiceProvider::class,
        ];
    }

    /**
     * No package auto-loads its migrations (they are publish-only), so the suite runs them
     * itself — exactly like a host app does after publishing. Every source is named by
     * **provider class**, never by filename or a hand-resolved path: the base case reflects
     * each provider to its own `database/migrations`, so this keeps working when a provider
     * renames a file or composer moves the package between a symlinked path repo and a real
     * VCS install.
     *
     *  - media-library ships the `media` table the comment attachments bucket persists into;
     *  - likes ships the `likes` table comment reactions persist into;
     *  - reports ships the `reports` table report-a-comment persists into;
     *  - approvals backs the multi-moderator moderation flow.
     *
     * @return list<class-string<ServiceProvider>|string>
     */
    protected function migrationSources(): array
    {
        return [
            MediaLibraryServiceProvider::class,
            LikesServiceProvider::class,
            ReportsServiceProvider::class,
            ApprovalsServiceProvider::class,
            CommentsServiceProvider::class,
            __DIR__.'/database/migrations',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function configBeforeBoot(): array
    {
        return [
            // Media-library: store on a fakeable public disk, use the GD driver, and keep
            // the responsive ladder small so variant generation stays fast under test.
            'media.disk' => 'public',
            'media.image_driver' => 'gd',
            'media.responsive.widths' => [320, 640],
        ];
    }
}
