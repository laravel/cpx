<?php

declare(strict_types=1);

namespace Cpx\Packages;

use Cpx\Input\PackageInvocation;
use Cpx\Process\ProcessRunner;
use InvalidArgumentException;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;

/**
 * Runs a non-built-in cpx target by resolving it to a local project binary,
 * a user alias, or a vendor/package and executing it.
 */
class PackageCommandRunner
{
    public function run(PackageInvocation $invocation, OutputInterface $output, bool $skipLocal = false): int
    {
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

        if (str_ends_with(strtolower($target), '.php') || is_file($target)) {
            info("To run a PHP file, use: cpx exec {$target}");
        }

        return SymfonyCommand::FAILURE;
    }
}
