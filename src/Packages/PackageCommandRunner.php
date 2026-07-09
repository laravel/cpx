<?php

declare(strict_types=1);

namespace Cpx\Packages;

use Cpx\Commands\ExecCommand;
use Cpx\Input\PackageInvocation;
use InvalidArgumentException;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\error;

/**
 * Runs a non-built-in cpx target by resolving it to a local PHP file, a user
 * alias, or a vendor/package and executing it.
 */
class PackageCommandRunner
{
    public function run(PackageInvocation $invocation, OutputInterface $output): int
    {
        if ($this->isFile($invocation->target)) {
            return (new ExecCommand)->run($this->fileInput($invocation), $output);
        }

        $userAlias = UserAliases::open()->find($invocation->target);

        if ($userAlias !== null) {
            return $userAlias->runCommand($invocation);
        }

        if (str_contains($invocation->target, '/')) {
            try {
                return Package::parse($invocation->target)->runCommand($invocation);
            } catch (InvalidArgumentException) {
                error("Unrecognised command {$invocation->target}");

                return SymfonyCommand::FAILURE;
            }
        }

        error("Unrecognised command {$invocation->target}");

        return SymfonyCommand::FAILURE;
    }

    private function isFile(string $path): bool
    {
        $realPath = realpath($path);

        return $realPath !== false && file_exists($realPath) && ! is_dir($realPath);
    }

    private function fileInput(PackageInvocation $invocation): ArrayInput
    {
        $input = ['file' => $invocation->target];

        foreach (['find-autoloader', 'load-laravel-bootstrap', 'alias-classes'] as $option) {
            if ($invocation->hasOption($option)) {
                $input["--{$option}"] = filter_var($invocation->option($option), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;
            }
        }

        return new ArrayInput($input);
    }
}
