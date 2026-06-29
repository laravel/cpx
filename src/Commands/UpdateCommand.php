<?php

declare(strict_types=1);

namespace Cpx\Commands;

use Cpx\Composer;
use Cpx\Package;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

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
            str_contains($target, '/') => $this->updatePackage(Package::parse($target), $output),
            $target !== '' => $this->updateVendor($target, $output),
            default => $this->updateAllPackages($output),
        };

        return self::SUCCESS;
    }

    protected function updateAllPackages(OutputInterface $output): void
    {
        $packageDirectories = glob(cpx_path('*/*/*'), GLOB_ONLYDIR) ?: [];

        if (empty($packageDirectories)) {
            $output->writeln('There are no packages to update.');
        } else {
            foreach ($packageDirectories as $directory) {
                $this->updateDirectory($directory, $output);
            }
        }
    }

    protected function updateVendor(string $vendor, OutputInterface $output): void
    {
        $packageDirectories = glob(cpx_path("{$vendor}/*/*"), GLOB_ONLYDIR) ?: [];

        if (empty($packageDirectories)) {
            $output->writeln("There are no packages in vendor '{$vendor}' to update.");
        } else {
            foreach ($packageDirectories as $directory) {
                $this->updateDirectory($directory, $output);
            }
        }
    }

    protected function updatePackage(Package $package, OutputInterface $output): void
    {
        if ($package->version) {
            $this->updateDirectory(cpx_path($package->folder()), $output);

            return;
        }

        $packageDirectories = glob(cpx_path("{$package->vendor}/{$package->name}/*"), GLOB_ONLYDIR) ?: [];

        if (empty($packageDirectories)) {
            $output->writeln("There are no installed versions of '{$package->vendor}/{$package->name}' to update.");
        } else {
            foreach ($packageDirectories as $directory) {
                $this->updateDirectory($directory, $output);
            }
        }
    }

    protected function updateDirectory(string $directory, OutputInterface $output): void
    {
        $output->writeln('Updating <info>'.str_replace(cpx_path(), '', $directory).'</info>');
        Composer::runCommand('update', $directory);
    }
}
