<?php

declare(strict_types=1);

namespace Cpx\Composer;

use Closure;
use Composer\Console\Application as ComposerApplication;
use Cpx\Exceptions\ComposerCommandException;
use Cpx\Process\ProcessRunner;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\OutputInterface;

class ComposerRunner
{
    public const REINVOKE_TOKEN = '__cpx_run_composer';

    /** @var (Closure(list<string>): int)|null */
    private static ?Closure $fake = null;

    /**
     * @param  list<string>  $arguments
     *
     * @throws ComposerCommandException
     */
    public static function run(array $arguments, ?string $directory = null, ComposerSource $source = ComposerSource::Bundled): int
    {
        $command = [...$arguments, '--no-interaction'];

        if ($directory !== null) {
            $command[] = "--working-dir={$directory}";
        }

        $exitCode = self::$fake !== null
            ? (self::$fake)($command)
            : (new ProcessRunner)->run([...$source->binary(), ...$command]);

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
}
