<?php

declare(strict_types=1);

namespace Cpx\Runtime;

use Phar;

class Environment
{
    private static ?string $fakePharPath = null;

    public static function pharPath(): string
    {
        return self::$fakePharPath ?? Phar::running(false);
    }

    public static function isPhar(): bool
    {
        return self::pharPath() !== '';
    }

    public static function fakePharPath(string $path): void
    {
        self::$fakePharPath = $path;
    }

    public static function clearFakePharPath(): void
    {
        self::$fakePharPath = null;
    }
}
