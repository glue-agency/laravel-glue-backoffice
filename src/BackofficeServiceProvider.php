<?php

namespace GlueAgency\Backoffice;

use GlueAgency\Backoffice\Console\ReportCommand;
use Illuminate\Support\ServiceProvider;

class BackofficeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/glue-backoffice.php', 'glue-backoffice');

        $this->app->singleton(Packages::class, function ($app) {
            return new Packages($app->basePath());
        });

        $this->app->singleton(InstallUid::class, function ($app) {
            return new InstallUid($app->storagePath() . '/app/glue-backoffice-uid');
        });
    }

    public function boot(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__ . '/../config/glue-backoffice.php' => $this->app->configPath('glue-backoffice.php'),
        ], 'glue-backoffice-config');

        $this->commands([
            ReportCommand::class,
        ]);
    }
}
