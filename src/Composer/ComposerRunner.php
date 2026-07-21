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
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\OutputInterface;

class ComposerRunner
{
    public const REINVOKE_TOKEN = '__cpx_run_composer';

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

        $exception = new ComposerCommandException($arguments);
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
    public static function run(array $arguments, ?string $directory = null): int
    {
        $exitCode = self::execute($arguments, $directory, captureOutput: false)->exitCode;

        if ($exitCode !== Command::SUCCESS) {
            throw new ComposerCommandException($arguments);
        }

        return $exitCode;
    }

    /**
     * @param  list<string>  $arguments
     */
    public static function runInProcess(array $arguments, ?OutputInterface $output = null): int
    {
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

        return $captureOutput
            ? $runner->runWithOutput($processCommand)
            : new ProcessResult($runner->run($processCommand), '');
    }

    private static function missingPackage(string $output): ?string
    {
        $output = preg_replace('/\x1B(?:[@-Z\\-_]|\[[0-?]*[ -\/]*[@-~])/', '', $output) ?? $output;
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
