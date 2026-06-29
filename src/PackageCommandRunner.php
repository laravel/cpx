<?php

declare(strict_types=1);

namespace Cpx;

use Cpx\Commands\ExecCommand;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\OutputInterface;

class PackageCommandRunner
{
    public function run(Console $console, OutputInterface $output): int
    {
        if ($this->isFile($console->command)) {
            return (new ExecCommand)->run($this->fileInput($console), $output);
        }

        if (array_key_exists($console->command, PackageAliases::$packages)) {
            Package::parse(PackageAliases::$packages[$console->command]['package'])->runCommand($console);

            return SymfonyCommand::SUCCESS;
        }

        if (str_contains($console->command, '/')) {
            Package::parse($console->command)->runCommand($console);

            return SymfonyCommand::SUCCESS;
        }

        $output->writeln("<error>Unrecognised command {$console->command}</error>");

        return SymfonyCommand::FAILURE;
    }

    private function isFile(string $path): bool
    {
        $realPath = realpath($path);

        return $realPath !== false && file_exists($realPath) && ! is_dir($realPath);
    }

    private function fileInput(Console $console): ArrayInput
    {
        $input = ['file' => $console->command];

        foreach (['find-autoloader', 'load-laravel-bootstrap', 'alias-classes'] as $option) {
            if ($console->hasOption($option)) {
                $input["--{$option}"] = filter_var($console->getOption($option), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;
            }
        }

        return new ArrayInput($input);
    }
}
