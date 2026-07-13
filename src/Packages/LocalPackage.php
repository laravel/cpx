<?php

declare(strict_types=1);

namespace Cpx\Packages;

use Cpx\Input\PackageInvocation;
use Cpx\Process\ProcessRunner;
use Cpx\Support\Filesystem;
use InvalidArgumentException;
use JsonException;
use Symfony\Component\Console\Command\Command;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;

readonly class LocalPackage
{
    /**
     * @param  array<string, string>  $binaries
     */
    private function __construct(
        public string $root,
        public string $name,
        private array $binaries,
    ) {
        //
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

    public static function fromPath(string $path): self
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
            binaries: self::binaries($composer),
        );
    }

    public function runCommand(PackageInvocation $invocation): int
    {
        if ($this->binaries === []) {
            error("No bin command found in {$this->root}.");

            return Command::FAILURE;
        }

        $resolved = BinResolver::resolve($this->binaries, $invocation, $this->name);

        if ($resolved === null) {
            error("More than 1 bin command found in {$this->root}: ".implode(', ', array_keys($this->binaries)).'.');

            return Command::FAILURE;
        }

        $binPath = Filesystem::joinPath($this->root, $resolved->command);

        if (! is_file($binPath)) {
            error('Command '.basename($resolved->command)." not found in {$this->root}.");

            return Command::FAILURE;
        }

        info('Running '.basename($resolved->command)." from {$this->root}");

        return (new ProcessRunner)->run(BinExecutable::commandFor($binPath, $resolved->invocation->forwardedTokens()));
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

    /**
     * @param  array<array-key, mixed>  $composer
     * @return array<string, string>
     */
    private static function binaries(array $composer): array
    {
        $declared = $composer['bin'] ?? [];
        $binaries = [];

        foreach ((array) $declared as $binary) {
            if (! is_string($binary) || $binary === '') {
                continue;
            }

            $binaries[basename(Filesystem::normalizePath($binary))] = $binary;
        }

        return $binaries;
    }
}
