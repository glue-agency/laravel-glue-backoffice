<?php

namespace GlueAgency\Backoffice\Tests;

use GlueAgency\Backoffice\BackofficeServiceProvider;
use GlueAgency\Backoffice\InstallUid;
use GlueAgency\Backoffice\Packages;
use Orchestra\Testbench\TestCase as Orchestra;

/**
 * An app named Fietsen Declercq that requires what tests/Fixtures/app's
 * composer.json does, running in production with the Dashboard's token set:
 * a production host, the only one that reports.
 */
abstract class TestCase extends Orchestra
{
    /**
     * Where this test keeps the app's uid, removed again after it.
     *
     * @var string
     */
    protected $uidPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->uidPath = sys_get_temp_dir() . '/glue-backoffice-' . bin2hex(random_bytes(6)) . '/uid';

        $this->app->instance(Packages::class, new Packages(__DIR__ . '/Fixtures/app'));
        $this->app->instance(InstallUid::class, new InstallUid($this->uidPath));
    }

    protected function tearDown(): void
    {
        if (is_file($this->uidPath)) {
            unlink($this->uidPath);
        }

        if (is_dir(dirname($this->uidPath))) {
            rmdir(dirname($this->uidPath));
        }

        parent::tearDown();
    }

    protected function getPackageProviders($app)
    {
        return [BackofficeServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['env'] = 'production';

        $app['config']->set('app.name', 'Fietsen Declercq');
        $app['config']->set('glue-backoffice.url', 'https://dashboard.invalid/');
        $app['config']->set('glue-backoffice.token', 'secret-token');
    }
}
