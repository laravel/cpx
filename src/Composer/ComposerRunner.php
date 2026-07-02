<?php

declare(strict_types=1);

namespace Cpx\Composer;

use Closure;
use Composer\InstalledVersions;
use Cpx\Exceptions\ComposerCommandException;
use Cpx\Process\ProcessRunner;
use RuntimeException;
use Symfony\Component\Console\Command\Command;

class ComposerRunner
{
    /** @var (Closure(list<string>): int)|null */
    private static ?Closure $fake = null;

    /**
     * Run a Composer command in an isolated cpx child process.
     *
     * @param  list<string>  $arguments
     *
     * @throws ComposerCommandException
     */
    public static function run(array $arguments, ?string $directory = null): int
    {
        $command = [...$arguments, '--no-interaction'];

        if ($directory !== null) {
            $command[] = "--working-dir={$directory}";
        }

        $exitCode = self::$fake !== null
            ? (self::$fake)($command)
            : (new ProcessRunner)->run([PHP_BINARY, self::composerBinary(), ...$command]);

        if ($exitCode !== Command::SUCCESS) {
            throw new ComposerCommandException($arguments);
        }

        return $exitCode;
    }

    /**
     * @param  Closure(list<string>): int  $runner
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

    public static function getCurrentVersion(string $directory): string
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
        $version = is_array($lockData) ? ($lockData['packages'][0]['version'] ?? null) : null;

        return is_string($version) ? $version : $unknown;
    }

    private static function composerBinary(): string
    {
        $path = InstalledVersions::getInstallPath('composer/composer');

        if ($path === null) {
            throw new RuntimeException('Unable to locate the bundled Composer binary.');
        }

        return "{$path}/bin/composer";
    }
}
