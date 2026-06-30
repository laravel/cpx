<?php

declare(strict_types=1);

namespace Cpx\Commands;

use Cpx\Cache\Metadata;
use Cpx\Support\Filesystem;
use RuntimeException;
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
        $all = $input->getOption('all') === true;
        $timeLimit = time() - ((int) $input->getOption('days') * 24 * 3600);

        $cleaned = Metadata::transaction(
            fn (Metadata $metadata): bool => $this->clean($metadata, $all, $timeLimit, $output),
        );

        if (! $cleaned) {
            $output->writeln('<info>There were no packages to clean.</info>');
        }

        return self::SUCCESS;
    }

    private function clean(Metadata $metadata, bool $all, int $timeLimit, OutputInterface $output): bool
    {
        $removed = [
            $this->removeStalePackages($metadata, $all, $timeLimit, $output),
            $this->removeStaleSandboxes($metadata, $all, $timeLimit, $output),
            $this->removeOrphanPackages($metadata, $output),
            $this->removeOrphanSandboxes($metadata, $output),
        ];

        return in_array(true, $removed);
    }

    private function removeStalePackages(Metadata $metadata, bool $all, int $timeLimit, OutputInterface $output): bool
    {
        $removedAny = false;

        foreach ($metadata->packages as $key => $packageMetadata) {
            if (! $all && ! $this->isStale($packageMetadata->lastRunAt, $timeLimit)) {
                continue;
            }

            $output->writeln("<info>Removing unused package {$packageMetadata->package}...</info>");

            if ($this->removeWithinRoot($packageMetadata->installPath(), $output)) {
                unset($metadata->packages[$key]);
                $removedAny = true;
            }
        }

        return $removedAny;
    }

    private function removeStaleSandboxes(Metadata $metadata, bool $all, int $timeLimit, OutputInterface $output): bool
    {
        $removedAny = false;

        foreach ($metadata->execCache as $key => $sandbox) {
            if (! $all && ($sandbox->lastRunAt ?? 0) >= $timeLimit) {
                continue;
            }

            $output->writeln("<info>Removing exec sandbox cache {$key}...</info>");

            if ($this->removeWithinRoot(cpx_path(".exec_cache/{$key}"), $output)) {
                unset($metadata->execCache[$key]);
                $removedAny = true;
            }
        }

        return $removedAny;
    }

    private function removeOrphanPackages(Metadata $metadata, OutputInterface $output): bool
    {
        $tracked = [];

        foreach ($metadata->packages as $key => $packageMetadata) {
            $tracked[$packageMetadata->installPath()] = $key;
        }

        $removedAny = false;

        foreach (glob(cpx_path('*/*/*'), GLOB_ONLYDIR) ?: [] as $directory) {
            $trackedKey = $tracked[$directory] ?? null;

            if ($trackedKey !== null && file_exists("{$directory}/vendor/autoload.php")) {
                continue;
            }

            $output->writeln('<info>Removing orphaned package cache '.str_replace(cpx_path(), '', $directory).'...</info>');

            if (! $this->removeWithinRoot($directory, $output)) {
                continue;
            }

            if ($trackedKey !== null) {
                unset($metadata->packages[$trackedKey]);
            }

            $removedAny = true;
        }

        return $removedAny;
    }

    private function removeOrphanSandboxes(Metadata $metadata, OutputInterface $output): bool
    {
        $removedAny = false;

        foreach (glob(cpx_path('.exec_cache/*'), GLOB_ONLYDIR) ?: [] as $directory) {
            if (array_key_exists(basename($directory), $metadata->execCache)) {
                continue;
            }

            $output->writeln('<info>Removing orphaned exec sandbox cache '.basename($directory).'...</info>');

            if ($this->removeWithinRoot($directory, $output)) {
                $removedAny = true;
            }
        }

        return $removedAny;
    }

    private function isStale(?string $lastRunAt, int $timeLimit): bool
    {
        if ($lastRunAt === null) {
            return true;
        }

        $lastRun = strtotime($lastRunAt);

        return $lastRun === false || $lastRun < $timeLimit;
    }

    private function removeWithinRoot(string $path, OutputInterface $output): bool
    {
        try {
            Filesystem::deleteDirectoryWithin($path, cpx_path());

            return true;
        } catch (RuntimeException $exception) {
            $output->writeln("<error>{$exception->getMessage()}</error>");

            return false;
        }
    }
}
