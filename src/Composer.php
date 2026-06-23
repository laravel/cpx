<?php

declare(strict_types=1);

namespace Cpx;

use Exception;

class Composer
{
    /** @return list<string> */
    public static function runCommand(string $command, ?string $directory = null): array
    {
        $workingDirectory = $directory ? "--working-dir={$directory}" : '';
        $process = proc_open(
            "composer {$command} --no-interaction --quiet {$workingDirectory}",
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
        );

        if (! is_resource($process)) {
            throw new Exception("Composer command failed: {$command}");
        }

        fclose($pipes[0]);

        $output = stream_get_contents($pipes[1]);
        $errorOutput = stream_get_contents($pipes[2]);

        fclose($pipes[1]);
        fclose($pipes[2]);

        $resultCode = proc_close($process);

        if ($resultCode !== 0) {
            $message = trim($errorOutput) ?: "Composer command failed: {$command}";

            throw new Exception($message);
        }

        if ($output === false || trim($output) === '') {
            return [];
        }

        return explode(PHP_EOL, trim($output));
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
