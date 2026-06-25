<?php

declare(strict_types=1);

namespace Cpx\Commands;

use Cpx\Metadata;
use Cpx\Package;
use Cpx\Utils;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'clean',
    description: 'Clean unused cpx package caches',
)]
class CleanCommand extends Command
{
    protected function configure(): void
    {
        $this->addOption('all', null, InputOption::VALUE_NONE, 'Clean all packages');
        $this->addOption('days', null, InputOption::VALUE_REQUIRED, 'Clean packages older than this number of days', '30');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $days = (int) $input->getOption('days');

        $metadata = Metadata::open();
        $timeLimit = time() - ($days * 24 * 3600);
        $cleanedSomething = false;

        foreach ($metadata->packages as $packageKey => $packageMetadata) {
            $lastRun = strtotime($packageMetadata->lastRunAt ?? '1970-01-01 00:00:00');

            if ($input->getOption('all') === true || $lastRun < $timeLimit) {
                $package = Package::parse($packageKey);
                $output->writeln("<info>Removing unused package {$package}...</info>");
                $package->delete();
                unset($metadata->packages[$packageKey]);
                $cleanedSomething = true;
            }
        }

        foreach ($metadata->execCache as $sandboxDir => $packageMetadata) {
            $lastRun = $packageMetadata['last_run'] ?? 0;

            if ($input->getOption('all') === true || $lastRun < $timeLimit) {
                $packageDirectory = cpx_path(".exec_cache/{$sandboxDir}");
                Utils::deleteDirectory($packageDirectory);
                $output->writeln("<info>Removing exec sandbox cache {$sandboxDir}...</info>");
                unset($metadata->execCache[$sandboxDir]);
                $cleanedSomething = true;
            }
        }

        $metadata->save();

        if (! $cleanedSomething) {
            $output->writeln('<info>There were no packages to clean.</info>');
        }

        return self::SUCCESS;
    }
}
