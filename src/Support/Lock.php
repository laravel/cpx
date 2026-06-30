<?php

declare(strict_types=1);

namespace Cpx\Support;

use Closure;
use RuntimeException;

class Lock
{
    /**
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public static function run(string $path, Closure $callback): mixed
    {
        $directory = dirname($path);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $handle = fopen($path, 'c');

        if ($handle === false) {
            throw new RuntimeException("Unable to open lock file {$path}.");
        }

        try {
            if (! flock($handle, LOCK_EX)) {
                throw new RuntimeException("Unable to acquire lock on {$path}.");
            }

            return $callback();
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
