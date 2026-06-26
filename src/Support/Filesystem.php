<?php

declare(strict_types=1);

namespace Cpx\Support;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class Filesystem
{
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
            $file->isDir() && ! $file->isLink()
                ? rmdir($file->getPathname())
                : unlink($file->getPathname());
        }

        rmdir($directory);
    }
}
