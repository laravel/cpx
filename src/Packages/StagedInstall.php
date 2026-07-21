<?php

declare(strict_types=1);

namespace Cpx\Packages;

use Closure;
use Cpx\Support\Filesystem;
use RuntimeException;
use Throwable;

/** Installs into a sibling ".installing.<pid>" staging directory and atomically swaps it into the target. */
class StagedInstall
{
    /**
     * @param  array<string, mixed>  $scaffold
     * @param  Closure(string): void  $install
     * @param  Closure(RuntimeException): Throwable  $finalizeFailure
     */
    public static function run(string $targetDir, array $scaffold, Closure $install, Closure $finalizeFailure): void
    {
        $cacheRoot = cpx_path();
        Filesystem::ensureDirectory($cacheRoot);

        $stagingDir = "{$targetDir}.installing.".getmypid();
        Filesystem::deleteDirectoryWithin($stagingDir, $cacheRoot);
        Filesystem::ensureDirectory($stagingDir);

        file_put_contents("{$stagingDir}/composer.json", json_encode($scaffold, JSON_PRETTY_PRINT));

        try {
            $install($stagingDir);
        } catch (Throwable $exception) {
            Filesystem::deleteDirectoryWithin($stagingDir, $cacheRoot);

            throw $exception;
        }

        try {
            Filesystem::replaceDirectory($stagingDir, $targetDir);
        } catch (RuntimeException $exception) {
            Filesystem::deleteDirectoryWithin($stagingDir, $cacheRoot);

            throw $finalizeFailure($exception);
        }
    }
}
