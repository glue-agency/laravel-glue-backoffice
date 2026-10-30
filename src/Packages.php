<?php

namespace GlueAgency\Backoffice;

use Composer\InstalledVersions;
use ReflectionClass;
use Throwable;

/**
 * The Composer packages the app requires directly: its root composer.json's
 * `require`, not its whole lock file, each at the version installed, with the
 * description its own composer.json gives. Laravel itself is among them, as
 * `laravel/framework`. `require-dev` stays out: it never reaches production.
 */
class Packages
{
    /**
     * @var string
     */
    private $root;

    /**
     * @param  string  $root  The directory holding the app's composer.json
     */
    public function __construct(string $root)
    {
        $this->root = rtrim($root, '/');
    }

    /**
     * @return array<int, array{name: string, version: string, description: string}>
     */
    public function collect(): array
    {
        $composer = self::readJson($this->root . '/composer.json');
        $installed = $this->installed();
        $packages = [];

        foreach (array_keys((array) ($composer['require'] ?? [])) as $name) {
            $name = (string) $name;

            if (self::isPlatformRequirement($name)) {
                continue;
            }

            $packages[] = [
                'name'        => $name,
                'version'     => self::version($name, $installed[$name] ?? []),
                'description' => (string) ($installed[$name]['description'] ?? ''),
            ];
        }

        return $packages;
    }

    /**
     * PHP, an extension (ext-intl), a library (lib-curl) or Composer's own API:
     * what the server provides rather than a package, and the only names
     * without a vendor.
     */
    public static function isPlatformRequirement(string $name): bool
    {
        return strpos($name, '/') === false;
    }

    /**
     * @param  array<string, mixed>  $installed
     */
    private static function version(string $name, array $installed): string
    {
        if (class_exists(InstalledVersions::class)) {
            try {
                $version = InstalledVersions::getPrettyVersion($name);

                if ($version) {
                    return (string) $version;
                }
            } catch (Throwable $e) {
                // Not installed as far as Composer knows: the record below decides.
            }
        }

        return (string) ($installed['version'] ?? 'dev');
    }

    /**
     * Composer's own record of what it installed, by name, for descriptions:
     * beside InstalledVersions with Composer 2, in the app's vendor otherwise.
     *
     * @return array<string, array<string, mixed>>
     */
    private function installed(): array
    {
        $file = $this->root . '/vendor/composer/installed.json';

        if (class_exists(InstalledVersions::class)) {
            $file = dirname((string) (new ReflectionClass(InstalledVersions::class))->getFileName()) . '/installed.json';
        }

        $data = self::readJson($file);
        $packages = $data['packages'] ?? $data;
        $byName = [];

        foreach ((array) $packages as $package) {
            if (is_array($package) && isset($package['name'])) {
                $byName[(string) $package['name']] = $package;
            }
        }

        return $byName;
    }

    /**
     * @return array<mixed>
     */
    private static function readJson(string $file): array
    {
        if (! is_file($file)) {
            return [];
        }

        $data = json_decode((string) file_get_contents($file), true);

        return is_array($data) ? $data : [];
    }
}
