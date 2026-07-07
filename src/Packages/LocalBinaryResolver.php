<?php

declare(strict_types=1);

namespace Cpx\Packages;

use Composer\Semver\Semver;
use Cpx\Input\PackageInvocation;
use UnexpectedValueException;

class LocalBinaryResolver
{
    public function resolve(Package $package, PackageInvocation $invocation): ?ResolvedBin
    {
        $project = LocalProject::discover();

        if ($project === null || $project->installedPackageDir($package->vendor, $package->name) === null) {
            return null;
        }

        if ($package->version !== null && ! $this->installedVersionSatisfies($project, $package, $package->version)) {
            return null;
        }

        $selected = (new BinSelector)->select($package->binaries($project->root), $invocation, $package->name, $package->bin);

        if ($selected === null) {
            return null;
        }

        $path = $project->binaryPath(basename($selected->command));

        return $path === null ? null : new ResolvedBin($path, $selected->invocation);
    }

    public function resolveBare(PackageInvocation $invocation): ?ResolvedBin
    {
        $path = LocalProject::discover()?->binaryPath($invocation->target);

        return $path === null ? null : new ResolvedBin($path, $invocation);
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
