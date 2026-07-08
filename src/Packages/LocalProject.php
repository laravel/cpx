<?php

declare(strict_types=1);

namespace Cpx\Packages;

use Cpx\Support\Filesystem;

readonly class LocalProject
{
    private function __construct(
        public string $root,
        public string $binDir,
    ) {
        //
    }

    public static function discover(?string $startDir = null): ?self
    {
        $startDir ??= getcwd() ?: null;

        if ($startDir === null) {
            return null;
        }

        $directory = realpath($startDir);

        while ($directory !== false) {
            $manifest = "{$directory}/composer.json";

            if (is_file($manifest)) {
                return self::fromManifest($directory, $manifest);
            }

            $parent = dirname($directory);

            if ($parent === $directory) {
                return null;
            }

            $directory = $parent;
        }

        return null;
    }

    public function binaryPath(string $name): ?string
    {
        $path = "{$this->resolvedBinDir()}/{$name}";

        return is_file($path) ? $path : null;
    }

    public function installedPackageDir(string $vendor, string $name): ?string
    {
        $path = "{$this->root}/vendor/{$vendor}/{$name}";

        return is_dir($path) ? $path : null;
    }

    public function installedVersion(string $vendor, string $name): ?string
    {
        foreach ($this->installedPackages() as $package) {
            if (($package['name'] ?? null) !== "{$vendor}/{$name}") {
                continue;
            }

            $version = $package['version'] ?? null;

            return is_string($version) ? $version : null;
        }

        return null;
    }

    private static function fromManifest(string $root, string $manifest): ?self
    {
        $contents = file_get_contents($manifest);

        if ($contents === false) {
            return null;
        }

        $data = json_decode($contents, true);

        if (! is_array($data)) {
            return null;
        }

        $binDir = $data['config']['bin-dir'] ?? 'vendor/bin';

        return new self($root, is_string($binDir) ? $binDir : 'vendor/bin');
    }

    private function resolvedBinDir(): string
    {
        return Filesystem::isAbsolutePath($this->binDir)
            ? $this->binDir
            : "{$this->root}/{$this->binDir}";
    }

    /** @return list<array<array-key, mixed>> */
    private function installedPackages(): array
    {
        $installed = "{$this->root}/vendor/composer/installed.json";

        if (! is_file($installed)) {
            return [];
        }

        $contents = file_get_contents($installed);

        if ($contents === false) {
            return [];
        }

        $data = json_decode($contents, true);

        if (! is_array($data) || ! isset($data['packages']) || ! is_array($data['packages'])) {
            return [];
        }

        return array_values(array_filter($data['packages'], 'is_array'));
    }
}
