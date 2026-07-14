<?php

declare(strict_types=1);

namespace RoundlyConsulting\Comments\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use ReflectionClass;
use RoundlyConsulting\Approvals\ApprovalsServiceProvider;
use RoundlyConsulting\Comments\CommentsServiceProvider;
use RoundlyConsulting\Likes\LikesServiceProvider;
use RoundlyConsulting\MediaLibrary\MediaLibraryServiceProvider;
use RoundlyConsulting\Reports\ReportsServiceProvider;

abstract class TestCase extends Orchestra
{
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

    /**
     * No package auto-loads its migrations (they are publish-only), so the suite runs
     * them itself — exactly like a host app does after publishing. Every provider's
     * schema is loaded by *directory*: each directory's filenames already sort into
     * dependency order, and naming the files here would break the moment a provider
     * renames one.
     */
    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // media-library ships the `media` table the comment attachments bucket persists into.
        // likes ships the `likes` table comment reactions persist into.
        // reports ships the `reports` table report-a-comment persists into, and the approvals
        // engine tables back its multi-moderator moderation flow.
        foreach ([
            MediaLibraryServiceProvider::class,
            LikesServiceProvider::class,
            ReportsServiceProvider::class,
            ApprovalsServiceProvider::class,
        ] as $provider) {
            $this->loadMigrationsFrom($this->migrationsPathFor($provider));
        }

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
     * A provider package's migrations directory, resolved from wherever composer put it
     * (a symlinked path repository locally, a real install from VCS on CI).
     *
     * @param  class-string<ServiceProvider>  $provider
     */
    private function migrationsPathFor(string $provider): string
    {
        $base = dirname((string) (new ReflectionClass($provider))->getFileName(), 2);

        return $base.'/database/migrations';
    }
}
