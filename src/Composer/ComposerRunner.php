<?php

declare(strict_types=1);

namespace Cpx\Composer;

use Closure;
use Composer\Console\Application as ComposerApplication;
use Composer\InstalledVersions;
use Cpx\Exceptions\ComposerCommandException;
use Cpx\Exceptions\PackageNotFoundException;
use Cpx\Process\ProcessResult;
use Cpx\Process\ProcessRunner;
use Cpx\Runtime\Environment;
use Cpx\Support\Str;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\OutputInterface;

class ComposerRunner
{
    public const REINVOKE_TOKEN = '__cpx_run_composer';

    private const MINIMUM_MEMORY_LIMIT = '1536M';

    /** @var (Closure(list<string>): (int|ProcessResult))|null */
    private static ?Closure $fake = null;

    /**
     * @throws ComposerCommandException
     * @throws PackageNotFoundException
     */
    public static function require(string $package, string $directory): int
    {
        $arguments = ['require', $package];
        $result = self::execute($arguments, $directory, captureOutput: true);

        if ($result->exitCode === Command::SUCCESS) {
            return $result->exitCode;
        }

        $exception = new ComposerCommandException($arguments, $result->output);
        $missingPackage = self::missingPackage($result->output);

        if ($missingPackage === null) {
            throw $exception;
        }

        $requestedPackage = explode(':', $package, 2)[0];

        throw PackageNotFoundException::fromPackageString(
            strcasecmp($requestedPackage, $missingPackage) === 0 ? $package : $missingPackage,
            previous: $exception,
        );
    }

    /**
     * @param  list<string>  $arguments
     *
     * @throws ComposerCommandException
     */
    public static function run(array $arguments, ?string $directory = null): void
    {
        $exitCode = self::execute($arguments, $directory, captureOutput: false)->exitCode;

        if ($exitCode !== Command::SUCCESS) {
            throw new ComposerCommandException($arguments);
        }
    }

    /**
     * @param  list<string>  $arguments
     */
    public static function runInProcess(array $arguments, ?OutputInterface $output = null): int
    {
        self::raiseMemoryLimit();

        $composer = new ComposerApplication;
        $composer->setAutoExit(false);

        return $composer->run(new ArgvInput(['composer', ...$arguments]), $output);
    }

    /**
     * @param  Closure(list<string>): (int|ProcessResult)  $runner
     */
    public static function fake(Closure $runner): void
    {
        self::$fake = $runner;
    }

    public static function clearFake(): void
    {
        self::$fake = null;
    }

    /** @return list<string> */
    public static function detectBinFromComposer(string $directory): array
    {
        $composerFile = "{$directory}/composer.json";

        if (! file_exists($composerFile)) {
            return [];
        }

        $contents = file_get_contents($composerFile);

        if ($contents === false) {
            return [];
        }

        $composerData = json_decode($contents, true);

        if (! is_array($composerData) || ! isset($composerData['bin'])) {
            return [];
        }

        return array_values((array) $composerData['bin']);
    }

    public static function getCurrentVersion(string $directory, string $package): string
    {
        $unknown = 'unknown';

        $composerLock = "{$directory}/composer.lock";

        if (! file_exists($composerLock)) {
            return $unknown;
        }

        $contents = file_get_contents($composerLock);

        if ($contents === false) {
            return $unknown;
        }

        $lockData = json_decode($contents, true);
        $lockedPackages = is_array($lockData) ? (array) ($lockData['packages'] ?? []) : [];

        foreach ($lockedPackages as $locked) {
            if (is_array($locked) && ($locked['name'] ?? null) === $package) {
                $version = $locked['version'] ?? null;

                return is_string($version) ? $version : $unknown;
            }
        }

        return $unknown;
    }

    /**
     * @param  list<string>  $arguments
     */
    private static function execute(array $arguments, ?string $directory, bool $captureOutput): ProcessResult
    {
        $command = [...$arguments, '--no-interaction'];

        if ($directory !== null) {
            $command[] = "--working-dir={$directory}";
        }

        if (self::$fake !== null) {
            $result = (self::$fake)($command);

            return is_int($result) ? new ProcessResult($result, '') : $result;
        }

        $runner = new ProcessRunner;
        $processCommand = [...self::composerBinary(), ...$command];

        // A wide COLUMNS keeps wrapped package names from defeating missingPackage()
        return $captureOutput
            ? $runner->runWithOutput($processCommand, ['COLUMNS' => '4096'])
            : new ProcessResult($runner->run($processCommand), '');
    }

    /** Mirrors the bin/composer bootstrap, which booting Composer in-process bypasses. */
    private static function raiseMemoryLimit(): void
    {
        if (! function_exists('ini_set')) {
            return;
        }

        if ($override = getenv('COMPOSER_MEMORY_LIMIT')) {
            @ini_set('memory_limit', $override);

            return;
        }

        $current = trim((string) ini_get('memory_limit'));

        if ($current !== '-1' && self::memoryInBytes($current) < self::memoryInBytes(self::MINIMUM_MEMORY_LIMIT)) {
            @ini_set('memory_limit', self::MINIMUM_MEMORY_LIMIT);
        }
    }

    private static function memoryInBytes(string $value): int
    {
        $bytes = (int) $value;

        return match (strtolower(substr($value, -1))) {
            'g' => $bytes * 1024 * 1024 * 1024,
            'm' => $bytes * 1024 * 1024,
            'k' => $bytes * 1024,
            default => $bytes,
        };
    }

    private static function missingPackage(string $output): ?string
    {
        $output = Str::stripAnsi($output);
        $output = preg_replace('/\s+/', ' ', $output) ?? $output;
        $package = '(?<package>[a-z0-9](?:[_.-]?[a-z0-9]+)*\/[a-z0-9](?:(?:[_.]?|-{0,2})[a-z0-9]+)*)';

        foreach ([
            '/Could not find package '.$package.'\./i',
            '/Could not find a matching version of package '.$package.'\./i',
            '/requires '.$package.'(?: (?:(?! -> |, it ).)+)?(?:, it| ->) could not be found in any version, there may be a typo in the package name\./i',
        ] as $pattern) {
            if (preg_match($pattern, $output, $matches) === 1) {
                return $matches['package'];
            }
        }

        return null;
    }

    /** @return list<string> */
    private static function composerBinary(): array
    {
        $pharPath = Environment::pharPath();

        return $pharPath === ''
            ? [PHP_BINARY, self::bundledComposerPath()]
            : [PHP_BINARY, $pharPath, self::REINVOKE_TOKEN];
    }

    private static function bundledComposerPath(): string
    {
        $path = InstalledVersions::getInstallPath('composer/composer');

        if ($path === null) {
            throw new RuntimeException('Unable to locate the bundled Composer binary.');
        }

        return "{$path}/bin/composer";
    }
}
