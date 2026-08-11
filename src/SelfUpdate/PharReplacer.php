<?php

declare(strict_types=1);

namespace Cpx\SelfUpdate;

use Closure;
use Cpx\Exceptions\SelfUpdateException;
use Cpx\Process\ProcessRunner;
use Cpx\Support\Filesystem;
use RuntimeException;

class PharReplacer
{
    public function __construct(
        private readonly ProcessRunner $processes = new ProcessRunner,
        private readonly bool $windows = PHP_OS_FAMILY === 'Windows',
    ) {
        //
    }

    /**
     * @param  Closure(string): void  $download  Writes the new PHAR to the given path.
     *
     * @throws SelfUpdateException
     */
    public function replace(string $target, Release $release, Closure $download): void
    {
        $directory = dirname($target);

        if (! is_writable($directory) || ($this->windows && ! is_writable($target))) {
            throw SelfUpdateException::notWritable($target);
        }

        $temporary = $this->workingPath($target, 'tmp');

        try {
            $download($temporary);
            $this->verifyChecksum($temporary, $release);
            $this->verifyRuns($temporary, $release);
        } catch (SelfUpdateException $exception) {
            $this->cleanup($temporary);

            throw $exception;
        }

        $permissions = fileperms($target);
        @chmod($temporary, $permissions === false ? 0755 : $permissions & 0777);

        $this->swap($temporary, $target);
    }

    /** @throws SelfUpdateException */
    private function verifyChecksum(string $temporary, Release $release): void
    {
        if ($release->sha256 === null) {
            return;
        }

        $actual = hash_file('sha256', $temporary);

        if ($actual === false || ! hash_equals($release->sha256, $actual)) {
            throw SelfUpdateException::checksumMismatch();
        }
    }

    /** @throws SelfUpdateException */
    private function verifyRuns(string $temporary, Release $release): void
    {
        $result = $this->processes->runWithOutput([PHP_BINARY, $temporary, '--version']);

        if ($result->exitCode !== 0 || ! str_contains($result->output, $release->tag)) {
            throw SelfUpdateException::validationFailed($release->tag);
        }
    }

    /** @throws SelfUpdateException */
    private function swap(string $temporary, string $target): void
    {
        $backup = $this->workingPath($target, 'backup');

        if (! @copy($target, $backup)) {
            $this->cleanup($temporary);

            throw SelfUpdateException::swapFailed($target);
        }

        try {
            Filesystem::replaceFile($temporary, $target, viaCopy: $this->windows);
        } catch (RuntimeException) {
            @copy($backup, $target);
            $this->cleanup($temporary, $backup);

            throw SelfUpdateException::swapFailed($target);
        }

        $this->cleanup($backup);
    }

    private function workingPath(string $target, string $suffix): string
    {
        return $target.'.'.bin2hex(random_bytes(8)).'.'.$suffix;
    }

    private function cleanup(string ...$paths): void
    {
        foreach ($paths as $path) {
            if (file_exists($path)) {
                @unlink($path);
            }
        }
    }
}
