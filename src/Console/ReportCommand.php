<?php

namespace GlueAgency\Backoffice\Console;

use GlueAgency\Backoffice\Client;
use GlueAgency\Backoffice\Report;
use Illuminate\Console\Command;
use Throwable;

/**
 * Reports the app to the Glue Dashboard, run by the deploy once the release is
 * live, from every environment it is deployed to: live sends the whole report,
 * any other only where it answers. A host without the install's token says so
 * and reports nothing. A refused report exits with 1, which fails the deploy,
 * as the Craft and Magento reporters do: the Dashboard's messages say what to
 * fix.
 */
class ReportCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'glue:backoffice:report
        {--repository-name= : The repository this app deploys from}
        {--dry-run : Print the report as JSON instead of sending it; needs no token}';

    /**
     * @var string
     */
    protected $description = 'Reports this app to the Glue Dashboard';

    public function handle(Report $report, Client $client): int
    {
        $repositoryName = $this->option('repository-name');
        $repositoryName = $repositoryName === null ? null : (string) $repositoryName;

        if ($this->option('dry-run')) {
            $this->line((string) json_encode($report->build($repositoryName, false), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return 0;
        }

        $environment = $report->environment();

        if ($environment === '') {
            $this->line('No environment set (APP_ENV), not reporting.');

            return 0;
        }

        $token = (string) config('glue-backoffice.token');

        if ($token === '') {
            $this->line("Running in the {$environment} environment, no Glue Dashboard token configured, not reporting.");

            return 0;
        }

        try {
            $payload = $report->build($repositoryName);
            [$success, $errors] = $client->report((string) config('glue-backoffice.url'), $token, $payload);
        } catch (Throwable $e) {
            $this->error('Could not report to the Glue Dashboard: ' . $e->getMessage());

            return 1;
        }

        if (! $success) {
            $this->error('The Glue Dashboard did not accept the report:');

            foreach ($errors as $error) {
                $this->error('    ' . $error);
            }

            return 1;
        }

        $this->reported($payload);

        return 0;
    }

    /**
     * Every value the report sent, so the deploy log shows what the Dashboard
     * was told, from live or not.
     *
     * @param  array<string, mixed>  $payload
     */
    private function reported(array $payload): void
    {
        $version = isset($payload['version']) ? " v'{$payload['version']}'" : '';

        $this->info("Reported Laravel '{$payload['title']}'{$version} from the {$payload['environment']} environment");

        if (isset($payload['phpVersion'])) {
            $this->line("    PHP v'{$payload['phpVersion']}'");
        }

        $this->line("    URL '" . ($payload['url'] ?? '') . "'");

        if (isset($payload['packages'])) {
            $this->info('Packages');

            foreach ($payload['packages'] as $package) {
                $this->line("    Package '{$package['name']}' v'{$package['version']}'");
            }
        }

        $this->info('Repository');
        $this->line("    Name '" . ($payload['repository']['name'] ?? '') . "'");
    }
}
