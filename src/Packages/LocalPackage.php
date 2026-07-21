<?php

declare(strict_types=1);

namespace Cpx\Packages;

use BadMethodCallException;
use Cpx\Support\Filesystem;
use InvalidArgumentException;
use JsonException;

class LocalPackage extends Package
{
    /**
     * @param  array<string, string>  $localBinaries
     */
    private function __construct(
        public string $root,
        string $name,
        private array $localBinaries,
    ) {
        parent::__construct(vendor: '', name: $name);
    }

    public static function supports(string $target): bool
    {
        if (Filesystem::isAbsolutePath($target)) {
            return true;
        }

        if (in_array($target, ['.', '..', '~'], true)) {
            return true;
        }

        foreach (['./', '../', '~/', '.\\', '..\\', '~\\'] as $prefix) {
            if (str_starts_with($target, $prefix)) {
                return true;
            }
        }

        return false;
    }

    public static function parse(string $path): self
    {
        $expandedPath = self::expandHomeDirectory($path);
        $root = realpath($expandedPath);

        if ($root === false) {
            throw new InvalidArgumentException("Local package directory '{$path}' does not exist.");
        }

        if (! is_dir($root)) {
            throw new InvalidArgumentException("Local package path '{$path}' is not a directory.");
        }

        $manifest = Filesystem::joinPath($root, 'composer.json');

        if (! is_file($manifest)) {
            throw new InvalidArgumentException("No composer.json file found in local package directory {$root}.");
        }

        if (! is_readable($manifest)) {
            throw new InvalidArgumentException("The composer.json file in {$root} is not readable.");
        }

        $contents = file_get_contents($manifest);

        if ($contents === false) {
            throw new InvalidArgumentException("Unable to read the composer.json file in {$root}.");
        }

        try {
            $decodedComposer = json_decode($contents, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new InvalidArgumentException("The composer.json file in {$root} is not valid JSON.");
        }

        if (! is_object($decodedComposer)) {
            throw new InvalidArgumentException("The composer.json file in {$root} must contain a JSON object.");
        }

        $composer = get_object_vars($decodedComposer);

        if (! is_file(Filesystem::joinPath($root, 'vendor/autoload.php'))) {
            throw new InvalidArgumentException("Dependencies are not installed for the local package at {$root}. Run `composer install` in that directory.");
        }

        return new self(
            root: $root,
            name: self::packageName($composer, $root),
            localBinaries: self::mapBinaries($composer['bin'] ?? []),
        );
    }

    public function fullPackageString(): string
    {
        return $this->root;
    }

    /**
     * @return array<string, string>
     */
    public function binaries(string $installDir): array
    {
        return $this->localBinaries;
    }

    public function installOrUpdatePackage(bool $updateCheck = true): string
    {
        return $this->root;
    }

    public function packagePath(string $installDir): string
    {
        return $this->root;
    }

    public function installPath(): string
    {
        throw self::noCacheLifecycle(__FUNCTION__);
    }

    public function delete(): void
    {
        throw self::noCacheLifecycle(__FUNCTION__);
    }

    public function isInstalled(): bool
    {
        throw self::noCacheLifecycle(__FUNCTION__);
    }

    public function shouldCheckForUpdates(): bool
    {
        throw self::noCacheLifecycle(__FUNCTION__);
    }

    protected function recordRun(): void
    {
        // Local packages are not tracked in the cache metadata.
    }

    private static function expandHomeDirectory(string $path): string
    {
        if ($path !== '~' && ! str_starts_with($path, '~/') && ! str_starts_with($path, '~\\')) {
            return $path;
        }

        $home = self::homeDirectory();

        return $path === '~'
            ? $home
            : Filesystem::joinPath($home, substr($path, 2));
    }

    private static function homeDirectory(): string
    {
        foreach (['HOME', 'USERPROFILE'] as $variable) {
            $home = $_SERVER[$variable] ?? getenv($variable);

            if (is_string($home) && $home !== '') {
                return rtrim($home, '/\\');
            }
        }

        $drive = $_SERVER['HOMEDRIVE'] ?? getenv('HOMEDRIVE');
        $homePath = $_SERVER['HOMEPATH'] ?? getenv('HOMEPATH');

        if (is_string($drive) && $drive !== '' && is_string($homePath) && $homePath !== '') {
            return rtrim("{$drive}{$homePath}", '/\\');
        }

        throw new InvalidArgumentException('Unable to expand the local package path because the home directory could not be determined.');
    }

    /**
     * @param  array<array-key, mixed>  $composer
     */
    private static function packageName(array $composer, string $root): string
    {
        $name = $composer['name'] ?? null;

        if (! is_string($name) || $name === '') {
            return basename($root);
        }

        return basename(Filesystem::normalizePath($name));
    }

    private static function noCacheLifecycle(string $method): BadMethodCallException
    {
        return new BadMethodCallException("{$method}() is not supported for local packages; they are not managed in the cpx package cache.");
    }
}
