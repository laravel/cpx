<?php

namespace Cpx;

use Exception;

class Composer
{
    /** @return list<string> */
    public static function runCommand(string $command, ?string $directory = null): array
    {
        $output = [];
        $workingDirectory = $directory ? "--working-dir={$directory}" : '';

        exec("composer {$command} --no-interaction --quiet {$workingDirectory}", $output, $resultCode);

        if ($resultCode !== 0) {
            throw new Exception("Composer command failed: {$command}");
        }

        return $output;
    }

    /**
     * Get a list of bin scripts from a package's composer.json file
     *
     * @return string[]
     */
    public static function detectBinFromComposer(string $directory): array
    {
        $composerFile = "{$directory}/composer.json";

        if (file_exists($composerFile)) {
            $contents = file_get_contents($composerFile);

            if ($contents === false) {
                return [];
            }

            $composerData = json_decode($contents, true);

            if (is_array($composerData) && isset($composerData['bin'])) {
                return (array) $composerData['bin'];
            }
        }

        return [];
    }

    public static function getCurrentVersion(string $directory): string
    {
        $composerLock = "{$directory}/composer.lock";

        if (file_exists($composerLock)) {
            $contents = file_get_contents($composerLock);

            if ($contents === false) {
                return 'unknown';
            }

            $lockData = json_decode($contents, true);
            $version = is_array($lockData) ? ($lockData['packages'][0]['version'] ?? null) : null;

            return is_string($version) ? $version : 'unknown';
        }

        return 'unknown';
    }
}
