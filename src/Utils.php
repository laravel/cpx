<?php

declare(strict_types=1);

namespace Cpx;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class Utils
{
    /**
     * @template TKey of array-key
     * @template TValue
     * @template TReturnKey of array-key
     * @template TReturnValue
     *
     * @param  callable(TKey, TValue): array<TReturnKey, TReturnValue>  $f
     * @param  array<TKey, TValue>  $a
     * @return array<TReturnKey, TReturnValue>
     */
    public static function arrayMapAssoc(callable $f, array $a): array
    {
        return array_merge(...array_map($f, array_keys($a), $a));
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
            if ($file->isDir() && ! $file->isLink()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }

        rmdir($directory);
    }
}
