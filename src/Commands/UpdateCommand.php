<?php

declare(strict_types=1);

namespace Cpx\Commands;

use Cpx\Composer\ComposerRunner;
use Cpx\Packages\Package;
use Cpx\Process\ProcessRunner;
use Cpx\Support\Filesystem;
use Laravel\Prompts\Support\Logger;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\info;
use function Laravel\Prompts\task;

#[AsCommand(
    name: 'update',
    description: 'Update installed cpx packages',
)]
class UpdateCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('target', InputArgument::OPTIONAL, 'Package or vendor to update');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $target = (string) $input->getArgument('target');

        match (true) {
            str_contains($target, '/') => $this->updatePackage(Package::parse($target)),
            $target !== '' => $this->updateVendor($target),
            default => $this->updateAllPackages(),
        };

        return self::SUCCESS;
    }

    protected function updateAllPackages(): void
    {
        $packageDirectories = glob(cpx_path('*/*/*'), GLOB_ONLYDIR) ?: [];

        if (empty($packageDirectories)) {
            info('There are no packages to update.');
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
            info("There are no packages in vendor '{$vendor}' to update.");
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
            info("There are no installed versions of '{$package->vendor}/{$package->name}' to update.");
        } else {
            foreach ($packageDirectories as $directory) {
                $this->updateDirectory($directory);
            }
        }
    }

    protected function updateDirectory(string $directory): void
    {
        $relative = ltrim(str_replace(Filesystem::normalizePath(cpx_path()), '', Filesystem::normalizePath($directory)), '/');
        $package = implode('/', array_slice(explode('/', $relative), 0, 2));

        task(
            label: "Updating {$relative}",
            callback: function (Logger $logger) use ($directory, $relative, $package): void {
                $previousVersion = ComposerRunner::getCurrentVersion($directory, $package);
                ProcessRunner::withLogger($logger, fn () => ComposerRunner::run(['update'], $directory));
                $newVersion = ComposerRunner::getCurrentVersion($directory, $package);

                if ($previousVersion !== $newVersion) {
                    $logger->success("{$relative} was upgraded from {$previousVersion} to {$newVersion}.");
                } else {
                    $logger->line("{$relative} is already up-to-date.");
                }
            },
            keepSummary: true,
        );
    }
}
