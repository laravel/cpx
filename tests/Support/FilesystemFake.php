<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * Overrides the global rename() inside the Cpx\Support namespace so tests can
 * induce the transient failures that Filesystem retries.
 */
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
