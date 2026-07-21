<?php

declare(strict_types=1);

namespace Cpx\Process;

use Closure;
use Cpx\Support\Interactivity;
use Laravel\Prompts\Support\Logger;
use RuntimeException;
use Symfony\Component\Process\Exception\ExceptionInterface;
use Symfony\Component\Process\Process;

class ProcessRunner
{
    public const COULD_NOT_EXECUTE = 127;

    protected static ?Logger $logger = null;

    private static ?Closure $fakeRunner = null;

    private static ?string $fakeInput = null;

    private bool $failedToWrite = false;

    public static function withLogger(Logger $logger, callable $callback): mixed
    {
        self::$logger = $logger;

        try {
            return $callback();
        } finally {
            self::$logger = null;
        }
    }

    /**
     * @param  list<string>  $command
     * @param  array<string, string|false>  $env
     */
    public function run(array $command, array $env = [], ?string $cwd = null): int
    {
        if (self::$fakeRunner !== null) {
            return (self::$fakeRunner)($command, $env, $cwd);
        }

        if ($this->isMissingExecutable($command[0] ?? null)) {
            return self::COULD_NOT_EXECUTE;
        }

        try {
            $process = new Process($command, cwd: $cwd, env: $env === [] ? null : $env, timeout: null);

            if (self::$logger !== null) {
                return $process->run($this->logOutput(...));
            }

            if (self::$fakeInput === null && Interactivity::isInteractive() && Process::isTtySupported()) {
                $process->setTty(true);

                return $process->run();
            }

            $process->setInput(self::$fakeInput ?? STDIN);

            return $process->run($this->writeOutput(...));
        } catch (ExceptionInterface) {
            return self::COULD_NOT_EXECUTE;
        }
    }

    /**
     * @param  callable(list<string>, array<string, string|false>, string|null): int  $runner
     */
    public static function fake(callable $runner): void
    {
        self::$fakeRunner = $runner(...);
    }

    public static function clearFake(): void
    {
        self::$fakeRunner = null;
    }

    public static function fakeInput(string $input): void
    {
        self::$fakeInput = $input;
    }

    public static function clearFakeInput(): void
    {
        self::$fakeInput = null;
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
        if (self::$logger === null) {
            return;
        }

        $message = rtrim($buffer);

        if ($message !== '') {
            self::$logger->line($message);
        }
    }

    private function writeOutput(string $type, string $buffer): void
    {
        // Process::__destruct drains the pipes again; only the first failed write may throw.
        if ($this->failedToWrite) {
            return;
        }

        if (@fwrite($type === Process::ERR ? STDERR : STDOUT, $buffer) === false) {
            $this->failedToWrite = true;

            throw new RuntimeException('Unable to write the child process output.');
        }
    }
}
