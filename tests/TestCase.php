<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\Tests;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;
use ReflectionClass;
use RoundlyConsulting\Approvals\ApprovalsServiceProvider;
use RoundlyConsulting\Comments\CommentsServiceProvider;
use RoundlyConsulting\Likes\LikesServiceProvider;
use RoundlyConsulting\MediaLibrary\MediaLibraryServiceProvider;
use RoundlyConsulting\Reports\ReportsServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpDatabase();
    }

    /** @return array<int, class-string> */
    protected function getPackageProviders($app): array
    {
        return [
            ApprovalsServiceProvider::class,
            LikesServiceProvider::class,
            MediaLibraryServiceProvider::class,
            ReportsServiceProvider::class,
            CommentsServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Media-library: store on a fakeable public disk, use the GD driver, and keep the
        // responsive ladder small so variant generation stays fast under test.
        $app['config']->set('media.disk', 'public');
        $app['config']->set('media.image_driver', 'gd');
        $app['config']->set('media.responsive.widths', [320, 640]);
    }

    private function setUpDatabase(): void
    {
        // The package publishes its migrations rather than auto-loading them, so the
        // suite runs them itself — exactly like a host app does after publishing.
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Media-library ships the `media` table the comment attachments bucket persists into.
        $mediaPackage = dirname((string) (new ReflectionClass(MediaLibraryServiceProvider::class))->getFileName(), 2);
        $this->loadMigrationsFrom($mediaPackage.'/database/migrations');

        // Likes ships the `likes` table comment reactions persist into.
        $likesPackage = dirname((string) (new ReflectionClass(LikesServiceProvider::class))->getFileName(), 2);
        $this->loadMigrationsFrom($likesPackage.'/database/migrations');

        // Reports ships the `reports` table report-a-comment persists into.
        $reportsPackage = dirname((string) (new ReflectionClass(ReportsServiceProvider::class))->getFileName(), 2);
        $this->loadMigrationsFrom($reportsPackage.'/database/migrations');

        // Approvals engine tables back reports' multi-moderator moderation flow.
        $this->loadApprovalsSchema();

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

    /**
     * Run the approvals engine migrations in dependency order; their tables back
     * the report-moderation flow (a Report is an approvals subject).
     */
    private function loadApprovalsSchema(): void
    {
        $base = dirname((string) (new ReflectionClass(ApprovalsServiceProvider::class))->getFileName(), 2);

        $migrations = [
            'create_approvals_table',
            'create_approval_requests_table',
            'add_v11_columns_to_approvals_table',
            'add_staging_to_approval_requests_table',
            'create_approval_request_stages_table',
            'create_approval_delegations_table',
        ];

        foreach ($migrations as $name) {
            $migration = require "{$base}/database/migrations/{$name}.php";

            if ($migration instanceof Migration) {
                $migration->up();
            }
        }
    }
}
