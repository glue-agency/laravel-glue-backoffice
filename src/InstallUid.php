<?php

namespace GlueAgency\Backoffice;

use Illuminate\Support\Str;

/**
 * The app's own uid, which every report carries. A Laravel app has none of its
 * own, so one is made on the first report and kept in storage, which a deploy
 * shares between releases.
 *
 * The Dashboard knows the app by its token, not by this uid: it never looks an
 * install up by it. So a uid that cannot be kept is no reason to fail a deploy,
 * and the report goes out with a new one.
 */
class InstallUid
{
    /**
     * @var string
     */
    private $path;

    public function __construct(string $path)
    {
        $this->path = $path;
    }

    /**
     * The kept uid, or a new one kept from here on.
     */
    public function get(): string
    {
        $uid = $this->read();

        if ($uid !== '') {
            return $uid;
        }

        $uid = (string) Str::uuid();

        $this->write($uid);

        return $uid;
    }

    /**
     * The kept uid, or a new one that is not kept: what a dry run reports.
     */
    public function peek(): string
    {
        $uid = $this->read();

        return $uid !== '' ? $uid : (string) Str::uuid();
    }

    private function read(): string
    {
        if (! is_file($this->path)) {
            return '';
        }

        return trim((string) @file_get_contents($this->path));
    }

    private function write(string $uid): void
    {
        $directory = dirname($this->path);

        if (! is_dir($directory)) {
            @mkdir($directory, 0755, true);
        }

        @file_put_contents($this->path, $uid . PHP_EOL);
    }
}
