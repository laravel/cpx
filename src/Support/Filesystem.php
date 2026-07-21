<?php

declare(strict_types=1);

namespace Cpx\Support;

use FilesystemIterator;
use RuntimeException;
use SplFileInfo;

class Filesystem
{
    public static function normalizePath(string $path): string
    {
        return str_replace('\\', '/', $path);
    }

    public static function joinPath(string $base, string ...$segments): string
    {
        $path = rtrim($base, '/\\');

        foreach ($segments as $segment) {
            $segment = trim($segment, '/');

            if ($segment !== '') {
                $path .= '/'.$segment;
            }
        }

        return $path;
    }

    public static function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/')
            || str_starts_with($path, '\\\\')
            || preg_match('/\A[a-zA-Z]:[\\\\\/]/', $path) === 1;
    }

    public static function homeDirectory(): ?string
    {
        foreach (['HOME', 'USERPROFILE'] as $variable) {
            $home = $_SERVER[$variable] ?? getenv($variable);

            if (is_string($home) && $home !== '') {
                return rtrim($home, '/\\');
            }
        }

        $drive = $_SERVER['HOMEDRIVE'] ?? getenv('HOMEDRIVE');
        $homePath = $_SERVER['HOMEPATH'] ?? getenv('HOMEPATH');

        if (is_string($drive) && $drive !== '' && is_string($homePath) && $homePath !== '') {
            return rtrim("{$drive}{$homePath}", '/\\');
        }

        return null;
    }

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

        // Retry transient (Windows file-lock) failures, mirroring replaceDirectory().
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            if ($attempt > 1) {
                usleep(100_000);
            }

            if (@rename($temporaryPath, $path)) {
                return;
            }
        }

        self::deleteFile($temporaryPath);

        throw new RuntimeException("Unable to move {$temporaryPath} to {$path}.");
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

    public static function pruneEmptyParents(string $path, string $root): void
    {
        $resolvedRoot = realpath($root);

        if ($resolvedRoot === false) {
            return;
        }

        $parent = realpath(dirname($path));

        while (
            $parent !== false &&
            $parent !== $resolvedRoot &&
            str_starts_with($parent, $resolvedRoot.DIRECTORY_SEPARATOR)
        ) {
            if (! @rmdir($parent)) {
                return;
            }

            $parent = realpath(dirname($parent));
        }
    }

    public static function deleteDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        foreach (new FilesystemIterator($directory, FilesystemIterator::SKIP_DOTS) as $file) {
            /** @var SplFileInfo $file */
            $path = $file->getPathname();

            if (! $file->isDir()) {
                self::removeEntry($path, rmdir: false);

                continue;
            }

            if ($file->isLink() || self::isJunction($path)) {
                self::removeEntry($path, rmdir: PHP_OS_FAMILY === 'Windows');

                continue;
            }

            self::deleteDirectory($path);
        }

        self::removeEntry($directory, rmdir: true);
    }

    /** Replace the target with the source, retrying transient Windows file-lock failures. */
    public static function replaceDirectory(string $source, string $target): void
    {
        $lastFailure = null;

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            if ($attempt > 1) {
                usleep(100_000);
            }

            try {
                self::deleteDirectory($target);
            } catch (RuntimeException $exception) {
                $lastFailure = $exception;

                continue;
            }

            if (@rename($source, $target)) {
                return;
            }
        }

        throw $lastFailure ?? new RuntimeException("Unable to move {$source} to {$target}.");
    }

    private static function removeEntry(string $path, bool $rmdir): void
    {
        if ($rmdir ? @rmdir($path) : @unlink($path)) {
            return;
        }

        // Clear a Windows read-only attribute and retry once.
        @chmod($path, 0666);

        if (! ($rmdir ? @rmdir($path) : @unlink($path))) {
            throw new RuntimeException("Unable to remove {$path}.");
        }
    }

    /** NTFS junctions look like plain directories, but realpath() resolves to their target. */
    private static function isJunction(string $path): bool
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            return false;
        }

        $parent = realpath(dirname($path));
        $resolved = realpath($path);

        if ($parent === false || $resolved === false) {
            return false;
        }

        return $resolved !== $parent.DIRECTORY_SEPARATOR.basename($path);
    }

    private static function deleteFile(string $path): void
    {
        if (file_exists($path)) {
            unlink($path);
        }
    }
}
