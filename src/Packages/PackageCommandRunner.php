<?php

declare(strict_types=1);

namespace Cpx\Packages;

use Cpx\Commands\ExecCommand;
use Cpx\Input\PackageInvocation;
use Cpx\Process\ProcessRunner;
use InvalidArgumentException;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;

/**
 * Runs a non-built-in cpx target by resolving it to a local PHP file, a local
 * project binary, a user alias, or a vendor/package and executing it.
 */
class PackageCommandRunner
{
    public function run(PackageInvocation $invocation, OutputInterface $output, bool $skipLocal = false): int
    {
        if ($this->isFile($invocation->target)) {
            return (new ExecCommand)->run($this->fileInput($invocation), $output);
        }

        try {
            $package = $this->findPackage($invocation->target);
        } catch (InvalidArgumentException) {
            return $this->unrecognised($invocation->target);
        }

        if (! $skipLocal) {
            $resolved = $package === null
                ? LocalBinaryResolver::resolveBare($invocation)
                : LocalBinaryResolver::resolve($package, $invocation);

            if ($resolved !== null) {
                return $this->runLocal($resolved);
            }
        }

        if ($package !== null) {
            return $package->runCommand($invocation);
        }

        return $this->unrecognised($invocation->target);
    }

    private function findPackage(string $target): ?Package
    {
        $alias = UserAliases::open()->find($target);

        if ($alias !== null) {
            return $alias;
        }

        return str_contains($target, '/') ? Package::parse($target) : null;
    }

    private function runLocal(ResolvedBin $resolved): int
    {
        info('Running '.basename($resolved->command)." from {$resolved->command}");

        return (new ProcessRunner)->run(BinExecutable::commandFor($resolved->command, $resolved->invocation->forwardedTokens()));
    }

    private function unrecognised(string $target): int
    {
        error("Unrecognised command {$target}");

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
