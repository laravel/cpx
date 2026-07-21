<?php

declare(strict_types=1);

namespace Cpx\Packages;

use Cpx\Input\PackageInvocation;
use Cpx\Process\ProcessRunner;
use Cpx\Support\Interactivity;
use Cpx\Support\Result;
use InvalidArgumentException;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\info;

/**
 * Runs a non-built-in cpx target by resolving it to a local PHP file, an
 * explicit package directory, a local project binary, a user alias, or a
 * vendor/package and executing it.
 */
class PackageCommandRunner
{
    public function run(PackageInvocation $invocation, OutputInterface $output, bool $skipLocal = false): int
    {
        if (LocalPackage::supports($invocation->target)) {
            return LocalPackage::parse($invocation->target)->runCommand($invocation, $output);
        }

        try {
            $package = $this->findPackage($invocation->target);
        } catch (InvalidArgumentException) {
            return $this->unrecognised($invocation->target, $output);
        }

        if ($package instanceof LocalPackage) {
            return $package->runCommand($invocation, $output);
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
            return $package->runCommand($invocation, $output);
        }

        return $this->unrecognised($invocation->target, $output);
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

    private function unrecognised(string $target, OutputInterface $output): int
    {
        $status = Result::failure($output, "Unrecognised command {$target}");

        if (Interactivity::isInteractive() && (str_ends_with(strtolower($target), '.php') || is_file($target))) {
            info("To run a PHP file, use: cpx exec {$target}");
        }

        return $status;
    }
}
