<?php

namespace GlueAgency\Backoffice;

use Illuminate\Contracts\Foundation\Application;

/**
 * What the app says about itself. From live, the report the Dashboard keeps
 * as its install's Laravel section: the app's name as its title, its URL,
 * Laravel's version, PHP's, and the packages it requires. Laravel has no
 * edition. From any other environment only where it answers there, which is
 * all the Dashboard keeps of one.
 */
class Report
{
    private const MAX_LENGTH = 255;

    /**
     * The names the Dashboard reads as live.
     */
    private const LIVE = ['production', 'prod', 'live'];

    /**
     * @var Application
     */
    private $app;

    /**
     * @var Packages
     */
    private $packages;

    /**
     * @var InstallUid
     */
    private $installUid;

    public function __construct(Application $app, Packages $packages, InstallUid $installUid)
    {
        $this->app = $app;
        $this->packages = $packages;
        $this->installUid = $installUid;
    }

    /**
     * The environment the app runs in, as `APP_ENV` names it.
     */
    public function environment(): string
    {
        return trim((string) $this->app->environment());
    }

    public static function isLive(string $environment): bool
    {
        return in_array(strtolower($environment), self::LIVE, true);
    }

    /**
     * Any other environment sends no version or uid, so a Dashboard too old to
     * know environments refuses it rather than file it as live.
     *
     * @param  bool  $keepUid  False on a dry run, which leaves no trace
     * @return array<string, mixed>
     */
    public function build(?string $repositoryName = null, bool $keepUid = true): array
    {
        $environment = $this->environment();

        $payload = [
            'platform'    => 'laravel',
            'environment' => self::limit($environment),
            'title'       => self::limit((string) $this->app->make('config')->get('app.name', 'Laravel')),
        ];

        $url = $this->url();

        if ($url !== null) {
            $payload['url'] = $url;
        }

        if (self::isLive($environment)) {
            $payload += [
                'version'    => self::limit($this->app->version()),
                'phpVersion' => PHP_VERSION,
                'uid'        => $keepUid ? $this->installUid->get() : $this->installUid->peek(),
                'packages'   => array_map(function (array $package) {
                    return array_map([self::class, 'limit'], $package);
                }, $this->packages->collect()),
            ];
        }

        if ($repositoryName !== null && $repositoryName !== '') {
            $payload['repository'] = ['name' => self::limit($repositoryName)];
        }

        return $payload;
    }

    /**
     * `APP_URL`, unless it is left at Laravel's own `http://localhost`, which
     * says nothing about where the app answers.
     */
    private function url(): ?string
    {
        $url = trim((string) $this->app->make('config')->get('app.url', ''));
        $host = (string) parse_url($url, PHP_URL_HOST);

        if ($url === '' || in_array($host, ['', 'localhost', '127.0.0.1'], true)) {
            return null;
        }

        return self::limit($url);
    }

    /**
     * Nothing the Dashboard keeps is longer.
     */
    public static function limit(string $value): string
    {
        return mb_substr($value, 0, self::MAX_LENGTH);
    }
}
