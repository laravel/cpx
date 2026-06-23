<?php

namespace Cpx\Commands;

use Cpx\Composer;
use Cpx\Package;

class UpdateCommand extends Command
{
    public function __invoke(): void
    {
        match (true) {
            str_contains($this->console->arguments[0] ?? '', '/') => $this->updatePackage(Package::parse($this->console->arguments[0])),
            ! empty($this->console->arguments[0]) => $this->updateVendor($this->console->arguments[0]),
            default => $this->updateAllPackages(),
        };
    }

    protected function updateAllPackages(): void
    {
        $packageDirectories = glob(cpx_path('*/*/*'), GLOB_ONLYDIR) ?: [];

        if (empty($packageDirectories)) {
            $this->line('There are no packages to update.');
        } else {
            foreach ($packageDirectories as $directory) {
                $this->updateDirectory($directory);
            }
        }
    }

    protected function updateVendor(string $vendor): void
    {
        $packageDirectories = glob(cpx_path("{$vendor}/*/*"), GLOB_ONLYDIR) ?: [];

        if (empty($packageDirectories)) {
            $this->line("There are no packages in vendor '{$vendor}' to update.");
        } else {
            foreach ($packageDirectories as $directory) {
                $this->updateDirectory($directory);
            }
        }
    }

    protected function updatePackage(Package $package): void
    {
        if ($package->version) {
            $this->updateDirectory(cpx_path($package->folder()));

            return;
        }

        $packageDirectories = glob(cpx_path("{$package->vendor}/{$package->name}/*"), GLOB_ONLYDIR) ?: [];

        if (empty($packageDirectories)) {
            $this->line("There are no installed versions of '{$package->vendor}/{$package->name}' to update.");
        } else {
            foreach ($packageDirectories as $directory) {
                $this->updateDirectory($directory);
            }
        }
    }

    protected function updateDirectory(string $directory): void
    {
        $this->line('Updating '.Command::COLOR_GREEN.str_replace(cpx_path(), '', $directory));
        Composer::runCommand('update', $directory);
    }
}
