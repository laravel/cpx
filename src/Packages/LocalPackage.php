<?php

declare(strict_types=1);

namespace Cpx\Packages;

use Cpx\Input\PackageInvocation;
use Cpx\Process\ProcessRunner;
use Cpx\Support\Filesystem;
use Cpx\Support\Result;
use InvalidArgumentException;
use JsonException;
use RuntimeException;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\info;

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
            localBinaries: self::manifestBinaries($composer),
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

    public function runCommand(PackageInvocation $invocation, OutputInterface $output, bool $autoUpdate = true): int
    {
        if ($this->localBinaries === []) {
            return Result::failure($output, "No bin command found in {$this->root}.");
        }

        $resolved = BinResolver::resolve($this->localBinaries, $invocation, $this->name, $this->bin);

        if ($resolved === null && $this->bin !== null) {
            throw new RuntimeException("The requested bin command '{$this->bin}' was not found in {$this}.");
        }

        $resolved ??= $this->chooseBinCommand($this->localBinaries, $invocation);

        if ($resolved === null) {
            return Result::failure($output, "More than 1 bin command found in {$this->root}: ".implode(', ', array_keys($this->localBinaries)).'.');
        }

        $binPath = Filesystem::joinPath($this->root, $resolved->command);

        if (! is_file($binPath)) {
            return Result::failure($output, 'Command '.basename($resolved->command)." not found in {$this->root}.");
        }

        info('Running '.basename($resolved->command)." from {$this->root}");

        return (new ProcessRunner)->run(BinExecutable::commandFor($binPath, $resolved->invocation->forwardedTokens()));
    }

    private static function expandHomeDirectory(string $path): string
    {
        if ($path !== '~' && ! str_starts_with($path, '~/') && ! str_starts_with($path, '~\\')) {
            return $path;
        }

        $home = Filesystem::homeDirectory()
            ?? throw new InvalidArgumentException('Unable to expand the local package path because the home directory could not be determined.');

        return $path === '~'
            ? $home
            : Filesystem::joinPath($home, substr($path, 2));
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
    private static function manifestBinaries(array $composer): array
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
