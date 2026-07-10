<?php

declare(strict_types=1);

namespace Cpx\Packages;

use Composer\Installer\BinaryInstaller;

/** Maps a bin path to a runnable argv: Windows cannot execute extensionless shebang scripts. */
class BinExecutable
{
    private static ?bool $fakeWindows = null;

    /**
     * @param  list<string>  $tokens
     * @return list<string>
     */
    public static function commandFor(string $path, array $tokens = []): array
    {
        if (self::$fakeWindows ?? PHP_OS_FAMILY === 'Windows') {
            foreach (['.bat', '.cmd'] as $extension) {
                if (is_file($path.$extension)) {
                    return [$path.$extension, ...$tokens];
                }
            }
        }

        if (is_file($path) && str_starts_with(BinaryInstaller::determineBinaryCaller($path), 'php')) {
            return [PHP_BINARY, $path, ...$tokens];
        }

        return [$path, ...$tokens];
    }

    public static function fakeWindows(bool $windows = true): void
    {
        self::$fakeWindows = $windows;
    }

    public static function clearFakeWindows(): void
    {
        self::$fakeWindows = null;
    }
}
