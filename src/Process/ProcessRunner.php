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
        $process = @proc_open($command, [STDIN, STDOUT, STDERR], $pipes);

        if (! is_resource($process)) {
            return self::COULD_NOT_EXECUTE;
        }

        return proc_close($process);
    }

    /**
     * @param  list<string>  $command
     * @return array{exitCode: int, stdout: string, stderr: string}
     */
    public function capture(array $command): array
    {
        $process = @proc_open($command, [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes);

        if (! is_resource($process)) {
            return [
                'exitCode' => self::COULD_NOT_EXECUTE,
                'stdout' => '',
                'stderr' => 'Failed to start process.',
            ];
        }

        fclose($pipes[0]);

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);

        fclose($pipes[1]);
        fclose($pipes[2]);

        return [
            'exitCode' => proc_close($process),
            'stdout' => $stdout === false ? '' : $stdout,
            'stderr' => $stderr === false ? '' : $stderr,
        ];
    }
}
