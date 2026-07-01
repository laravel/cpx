<?php

declare(strict_types=1);

namespace Cpx\Packages;

use Composer\Semver\Semver;
use Cpx\Composer\ComposerRunner;
use Cpx\Input\PackageInvocation;
use InvalidArgumentException;
use UnexpectedValueException;

class LocalBinaryResolver
{
    public function resolve(PackageInvocation $invocation): ?ResolvedBin
    {
        $project = LocalProject::discover();

        if ($project === null) {
            return null;
        }

        return str_contains($invocation->target, '/')
            ? $this->resolvePackage($project, $invocation)
            : $this->resolveNamed($project, $invocation);
    }

    private function resolveNamed(LocalProject $project, PackageInvocation $invocation): ?ResolvedBin
    {
        $aliases = PackageAliases::all();
        $name = $aliases[$invocation->target]->command ?? $invocation->target;
        $path = $project->binaryPath($name);

        return $path === null ? null : new ResolvedBin($path, $invocation);
    }

    private function resolvePackage(LocalProject $project, PackageInvocation $invocation): ?ResolvedBin
    {
        try {
            $package = Package::parse($invocation->target);
        } catch (InvalidArgumentException) {
            return null;
        }

        $packageDir = $project->installedPackageDir($package->vendor, $package->name);

        if ($packageDir === null) {
            return null;
        }

        if ($package->version !== null && ! $this->installedVersionSatisfies($project, $package, $package->version)) {
            return null;
        }

        $selected = (new BinSelector)->select($this->localBinNames($packageDir), $invocation, $package->name);

        if ($selected === null) {
            return null;
        }

        $path = $project->binaryPath($selected->command);

        return $path === null ? null : new ResolvedBin($path, $selected->invocation);
    }

    /** @return array<string, string> */
    private function localBinNames(string $packageDir): array
    {
        $names = array_map('basename', ComposerRunner::detectBinFromComposer($packageDir));

        return array_combine($names, $names);
    }

    private function installedVersionSatisfies(LocalProject $project, Package $package, string $constraint): bool
    {
        $installed = $project->installedVersion($package->vendor, $package->name);

        if ($installed === null) {
            return false;
        }

        try {
            return Semver::satisfies($installed, $constraint);
        } catch (UnexpectedValueException) {
            return false;
        }
    }
}
