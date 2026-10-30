<?php

namespace GlueAgency\Backoffice;

use Illuminate\Contracts\Foundation\Application;

/**
 * What the app says about itself: the report the Dashboard keeps as its
 * install's Laravel section. The app's name as its title, Laravel's version,
 * PHP's, and the packages it requires. Laravel has no edition.
 */
class Report
{
    private const MAX_LENGTH = 255;

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
     * @param  bool  $keepUid  False on a dry run, which leaves no trace
     * @return array<string, mixed>
     */
    public function build(?string $repositoryName = null, bool $keepUid = true): array
    {
        $payload = [
            'platform'   => 'laravel',
            'title'      => self::limit((string) $this->app->make('config')->get('app.name', 'Laravel')),
            'version'    => self::limit($this->app->version()),
            'phpVersion' => PHP_VERSION,
            'uid'        => $keepUid ? $this->installUid->get() : $this->installUid->peek(),
            'packages'   => array_map(function (array $package) {
                return array_map([self::class, 'limit'], $package);
            }, $this->packages->collect()),
        ];

        if ($repositoryName !== null && $repositoryName !== '') {
            $payload['repository'] = ['name' => self::limit($repositoryName)];
        }

        return $payload;
    }

    /**
     * Nothing the Dashboard keeps is longer.
     */
    public static function limit(string $value): string
    {
        return mb_substr($value, 0, self::MAX_LENGTH);
    }
}
