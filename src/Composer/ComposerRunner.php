<?php

declare(strict_types=1);

namespace Cpx\Composer;

use Cpx\Process\ProcessRunner;
use Exception;
use Symfony\Component\Console\Command\Command;

class ComposerRunner
{
    /**
     * @param  list<string>  $arguments
     */
    public static function run(array $arguments, ?string $directory = null): int
    {
        $command = ['composer', ...$arguments, '--no-interaction'];

        if ($directory !== null) {
            $command[] = "--working-dir={$directory}";
        }

        $exitCode = (new ProcessRunner)->run($command);

        if ($exitCode !== Command::SUCCESS) {
            throw new Exception('Composer command failed: '.implode(' ', $arguments));
        }

        return $exitCode;
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
