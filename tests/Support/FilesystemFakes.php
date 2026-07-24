<?php

declare(strict_types=1);

namespace Cpx\Support;

function rename(string $from, string $to): bool
{
    if (FilesystemFake::$failingRenames > 0) {
        FilesystemFake::$failingRenames--;

        return false;
    }

    return \rename($from, $to);
}

class FilesystemFake
{
    public static int $failingRenames = 0;
}
