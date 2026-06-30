<?php

declare(strict_types=1);

namespace Cpx\Process;

class ProcessRunner
{
    public const COULD_NOT_EXECUTE = 127;

    /**
     * @param  list<string>  $command
     */
    public function run(array $command): int
    {
        $stdin = fopen('php://fd/0', 'r');
        $stdout = fopen('php://fd/1', 'w');
        $stderr = fopen('php://fd/2', 'w');

        if ($stdin === false || $stdout === false || $stderr === false) {
            $this->closeAll($stdin, $stdout, $stderr);

            return self::COULD_NOT_EXECUTE;
        }

        try {
            $process = @proc_open($command, [$stdin, $stdout, $stderr], $pipes);

            if (! is_resource($process)) {
                return self::COULD_NOT_EXECUTE;
            }

            $exitCode = proc_close($process);

            return $exitCode === -1 ? self::COULD_NOT_EXECUTE : $exitCode;
        } finally {
            $this->closeAll($stdin, $stdout, $stderr);
        }
    }

    /**
     * @param  resource|false  ...$streams
     */
    private function closeAll(...$streams): void
    {
        foreach ($streams as $stream) {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }
}
