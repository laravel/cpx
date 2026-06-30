<?php

declare(strict_types=1);

namespace Cpx\Support;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

class Filesystem
{
    public static function ensureDirectory(string $directory): void
    {
        if (is_dir($directory)) {
            return;
        }

        if (! @mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new RuntimeException("Unable to create directory {$directory}.");
        }
    }

    public static function writeAtomic(string $path, string $contents): void
    {
        self::ensureDirectory(dirname($path));

        $temporaryPath = $path.'.'.getmypid().'.tmp';

        if (file_put_contents($temporaryPath, $contents) === false) {
            self::deleteFile($temporaryPath);

            throw new RuntimeException("Unable to write to {$temporaryPath}.");
        }

        if (! rename($temporaryPath, $path)) {
            self::deleteFile($temporaryPath);

            throw new RuntimeException("Unable to move {$temporaryPath} to {$path}.");
        }
    }

    public static function deleteDirectoryWithin(string $path, string $root): void
    {
        $resolvedRoot = realpath($root);

        if ($resolvedRoot === false) {
            throw new RuntimeException("Cache root {$root} does not exist.");
        }

        $resolvedPath = realpath($path);

        if ($resolvedPath === false) {
            return;
        }

        if (
            $resolvedPath !== $resolvedRoot &&
            ! str_starts_with($resolvedPath, $resolvedRoot.DIRECTORY_SEPARATOR)
        ) {
            throw new RuntimeException("Refusing to delete {$path} outside of the cpx cache root.");
        }

        self::deleteDirectory($resolvedPath);
    }

    public static function deleteDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($files as $file) {
            /** @var SplFileInfo $file */
            $path = $file->getPathname();
            $removed = $file->isDir() && ! $file->isLink() ? rmdir($path) : unlink($path);

            if (! $removed) {
                throw new RuntimeException("Unable to remove {$path}.");
            }
        }

        if (! rmdir($directory)) {
            throw new RuntimeException("Unable to remove directory {$directory}.");
        }
    }

    private static function deleteFile(string $path): void
    {
        if (file_exists($path)) {
            unlink($path);
        }
    }
}
