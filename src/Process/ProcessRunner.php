<?php

declare(strict_types=1);

namespace Cpx\Process;

use Laravel\Prompts\Support\Logger;
use Symfony\Component\Process\Exception\ExceptionInterface;
use Symfony\Component\Process\Process;

class ProcessRunner
{
    public const COULD_NOT_EXECUTE = 127;

    protected static ?Logger $logger = null;

    public static function withLogger(Logger $logger, callable $callback): mixed
    {
        static::$logger = $logger;

        try {
            return $callback();
        } finally {
            static::$logger = null;
        }
    }

    /**
     * @param  list<string>  $command
     */
    public function run(array $command): int
    {
        if ($this->isMissingExecutable($command[0] ?? null)) {
            return self::COULD_NOT_EXECUTE;
        }

        try {
            $process = new Process($command, timeout: null);

            if (static::$logger !== null) {
                return $process->run($this->logOutput(...));
            }

            if (Process::isTtySupported()) {
                $process->setTty(true);

                return $process->run();
            }

            return $process->run($this->writeOutput(...));
        } catch (ExceptionInterface) {
            return self::COULD_NOT_EXECUTE;
        }
    }

    private function isMissingExecutable(?string $command): bool
    {
        if ($command === null || strpbrk($command, '/\\') === false) {
            return false;
        }

        // Windows is_executable() rejects .bat/.cmd scripts
        if (PHP_OS_FAMILY === 'Windows') {
            return ! is_file($command);
        }

        return ! is_executable($command);
    }

    private function logOutput(string $type, string $buffer): void
    {
        if (static::$logger === null) {
            return;
        }

        $message = rtrim($buffer);

        if ($message !== '') {
            static::$logger->line($message);
        }
    }

    private function writeOutput(string $type, string $buffer): void
    {
        fwrite($type === Process::ERR ? STDERR : STDOUT, $buffer);
    }
}
