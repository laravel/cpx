<?php

declare(strict_types=1);

namespace Cpx;

use Cpx\Commands\ExecCommand;
use Cpx\Commands\HelpCommand;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

class PackageCommandRunner
{
    public function run(Console $console): int
    {
        if ($this->isFile($console->command)) {
            (new ExecCommand($this->fileConsole($console)))();

            return SymfonyCommand::SUCCESS;
        }

        if (array_key_exists($console->command, PackageAliases::$packages)) {
            Package::parse(PackageAliases::$packages[$console->command]['package'])->runCommand($console);

            return SymfonyCommand::SUCCESS;
        }

        if (str_contains($console->command, '/')) {
            Package::parse($console->command)->runCommand($console);

            return SymfonyCommand::SUCCESS;
        }

        (new HelpCommand($console))(true);

        return SymfonyCommand::FAILURE;
    }

    private function isFile(string $path): bool
    {
        $realPath = realpath($path);

        return $realPath !== false && file_exists($realPath) && ! is_dir($realPath);
    }

    private function fileConsole(Console $console): Console
    {
        return new Console(
            rawInput: $console->rawInput,
            command: 'exec',
            arguments: [$console->command, ...$console->arguments],
            options: $console->options,
            flags: $console->flags,
        );
    }
}
