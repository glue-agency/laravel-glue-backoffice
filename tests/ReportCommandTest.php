<?php

namespace GlueAgency\Backoffice\Tests;

use Composer\InstalledVersions;
use GlueAgency\Backoffice\InstallUid;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

/**
 * What a deploy runs once the release is live: the report it sends, and what
 * makes it fail the deploy (a refusal, a Dashboard out of reach) or report
 * nothing at all (any environment but production, no token).
 */
class ReportCommandTest extends TestCase
{
    public function test_it_reports_the_app_its_packages_and_its_repository_with_the_installs_token(): void
    {
        Http::fake(['https://dashboard.invalid/*' => Http::response(['success' => true])]);

        $this->artisan('glue:backoffice:report', ['--repository-name' => 'git@bitbucket.org:glue-team/declercq.git'])
            ->expectsOutput("Reported Laravel 'Fietsen Declercq' v'" . $this->app->version() . "'")
            ->expectsOutput("    Name 'git@bitbucket.org:glue-team/declercq.git'")
            ->assertExitCode(0);

        Http::assertSent(function (Request $request) {
            $packages = array_column($request['packages'], null, 'name');

            return $request->url() === 'https://dashboard.invalid/actions/backoffice/report'
                && $request->method() === 'POST'
                && $request->hasHeader('Authorization', 'Bearer secret-token')
                && $request->isForm()
                && $request['platform'] === 'laravel'
                && $request['title'] === 'Fietsen Declercq'
                && $request['version'] === $this->app->version()
                && $request['phpVersion'] === PHP_VERSION
                && $request['repository'] === ['name' => 'git@bitbucket.org:glue-team/declercq.git']
                && ! array_key_exists('edition', $request->data())
                && array_keys($packages) === ['guzzlehttp/guzzle', 'laravel/framework', 'glue-agency/not-installed']
                && $packages['laravel/framework']['version'] === InstalledVersions::getPrettyVersion('laravel/framework')
                && $packages['laravel/framework']['description'] !== ''
                && $packages['glue-agency/not-installed'] === ['name' => 'glue-agency/not-installed', 'version' => 'dev', 'description' => ''];
        });
    }

    public function test_without_a_repository_the_report_leaves_it_out(): void
    {
        Http::fake(['https://dashboard.invalid/*' => Http::response(['success' => true])]);

        $this->artisan('glue:backoffice:report')->assertExitCode(0);

        Http::assertSent(function (Request $request) {
            return ! array_key_exists('repository', $request->data());
        });
    }

    public function test_the_uid_is_made_on_the_first_report_and_kept_for_every_next_one(): void
    {
        Http::fake(['https://dashboard.invalid/*' => Http::response(['success' => true])]);

        $this->artisan('glue:backoffice:report')->assertExitCode(0);
        $this->artisan('glue:backoffice:report')->assertExitCode(0);

        $uids = [];

        Http::assertSent(function (Request $request) use (&$uids) {
            $uids[] = $request['uid'];

            return true;
        });

        $this->assertCount(2, $uids);
        $this->assertSame($uids[0], $uids[1]);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $uids[0]);
        $this->assertSame($uids[0], trim((string) file_get_contents($this->uidPath)));
    }

    public function test_a_uid_that_cannot_be_kept_does_not_stop_the_report(): void
    {
        Http::fake(['https://dashboard.invalid/*' => Http::response(['success' => true])]);

        // A file where the uid's directory would go: nothing can be written under it.
        $blocked = sys_get_temp_dir() . '/glue-backoffice-blocked-' . bin2hex(random_bytes(6));
        touch($blocked);
        $this->app->instance(InstallUid::class, new InstallUid($blocked . '/uid'));

        $this->artisan('glue:backoffice:report')->assertExitCode(0);

        unlink($blocked);

        Http::assertSent(function (Request $request) {
            return $request['uid'] !== '';
        });
    }

    public function test_a_dry_run_prints_the_report_sends_nothing_and_keeps_no_uid(): void
    {
        Http::fake();
        $this->app['config']->set('glue-backoffice.token', null);

        $this->assertSame(0, Artisan::call('glue:backoffice:report', ['--dry-run' => true, '--repository-name' => 'git@bitbucket.org:glue-team/declercq.git']));

        $payload = json_decode(Artisan::output(), true);

        $this->assertSame('laravel', $payload['platform']);
        $this->assertSame('Fietsen Declercq', $payload['title']);
        $this->assertSame(['name' => 'git@bitbucket.org:glue-team/declercq.git'], $payload['repository']);
        $this->assertCount(3, $payload['packages']);

        Http::assertNothingSent();
        $this->assertFileDoesNotExist($this->uidPath);
    }

    public function test_every_value_is_cut_to_what_the_dashboard_keeps(): void
    {
        $this->app['config']->set('app.name', str_repeat('Declercq ', 40));

        Artisan::call('glue:backoffice:report', ['--dry-run' => true, '--repository-name' => str_repeat('r', 300)]);

        $payload = json_decode(Artisan::output(), true);

        $this->assertSame(255, mb_strlen($payload['title']));
        $this->assertSame(255, mb_strlen($payload['repository']['name']));
    }

    public function test_without_a_token_it_reports_nothing(): void
    {
        Http::fake();
        $this->app['config']->set('glue-backoffice.token', null);

        $this->artisan('glue:backoffice:report')
            ->expectsOutput('No Glue Dashboard token configured, not reporting.')
            ->assertExitCode(0);

        Http::assertNothingSent();
    }

    public function test_outside_production_it_reports_nothing_even_with_a_token(): void
    {
        Http::fake();

        foreach (['staging', 'local', 'testing'] as $environment) {
            $this->app->detectEnvironment(function () use ($environment) {
                return $environment;
            });

            $this->artisan('glue:backoffice:report')
                ->expectsOutput("Running in the {$environment} environment, not reporting: only production reports.")
                ->assertExitCode(0);
        }

        Http::assertNothingSent();
    }

    public function test_a_refused_report_lists_every_reason_and_fails_the_deploy(): void
    {
        Http::fake(['https://dashboard.invalid/*' => Http::response([
            'success' => false,
            'errors'  => [
                'declercq.be' => ['This install is in the trash on the Dashboard. Restore it there before deploying again.'],
                'packages.0.version' => ['The packages.0.version field is required.'],
            ],
        ], 400)]);

        $this->artisan('glue:backoffice:report')
            ->expectsOutput('The Glue Dashboard did not accept the report:')
            ->expectsOutput('    declercq.be: This install is in the trash on the Dashboard. Restore it there before deploying again.')
            ->expectsOutput('    packages.0.version: The packages.0.version field is required.')
            ->assertExitCode(1);
    }

    public function test_an_unknown_token_fails_with_the_dashboards_message(): void
    {
        Http::fake(['https://dashboard.invalid/*' => Http::response(['success' => false, 'errors' => ['token' => ['Unauthorized.']]], 401)]);

        $this->artisan('glue:backoffice:report')
            ->expectsOutput('    token: Unauthorized.')
            ->assertExitCode(1);
    }

    public function test_an_error_page_of_laravels_own_fails_with_its_message(): void
    {
        Http::fake(['https://dashboard.invalid/*' => Http::response(['message' => 'Server Error'], 500)]);

        $this->artisan('glue:backoffice:report')
            ->expectsOutput('    Server Error')
            ->assertExitCode(1);
    }

    public function test_an_answer_that_is_not_the_dashboards_fails_with_what_came_back(): void
    {
        Http::fake(['https://dashboard.invalid/*' => Http::response('Bad gateway', 502)]);

        $this->artisan('glue:backoffice:report')
            ->expectsOutput('    Unexpected response (HTTP 502): Bad gateway')
            ->assertExitCode(1);
    }

    public function test_a_dashboard_out_of_reach_fails_the_deploy(): void
    {
        Http::fake(function () {
            throw new ConnectionException('cURL error 6: Could not resolve host: dashboard.invalid');
        });

        $this->artisan('glue:backoffice:report')
            ->expectsOutput('Could not report to the Glue Dashboard: cURL error 6: Could not resolve host: dashboard.invalid')
            ->assertExitCode(1);
    }
}
