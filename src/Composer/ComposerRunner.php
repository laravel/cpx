<?php

declare(strict_types=1);

namespace Cpx\Composer;

use Closure;
use Composer\Console\Application;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArgvInput;

class ComposerRunner
{
    /** @var (Closure(list<string>): int)|null */
    private static ?Closure $fake = null;

    /**
     * @param  list<string>  $arguments
     */
    public static function run(array $arguments, ?string $directory = null): int
    {
        $command = [...$arguments, '--no-interaction'];

        if ($directory !== null) {
            $command[] = "--working-dir={$directory}";
        }

        $runner = self::$fake ?? self::execute(...);
        $exitCode = $runner($command);

        if ($exitCode !== Command::SUCCESS) {
            throw new Exception('Composer command failed: '.implode(' ', $arguments));
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

    /**
     * @param  list<string>  $command
     */
    private static function execute(array $command): int
    {
        $workingDirectory = getcwd();

        try {
            $application = new Application;
            $application->setAutoExit(false);
            $application->setCatchExceptions(true);

            return $application->run(new ArgvInput(['composer', ...$command]));
        } finally {
            if ($workingDirectory !== false) {
                chdir($workingDirectory);
            }
        }
    }
}
