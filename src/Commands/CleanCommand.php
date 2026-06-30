<?php

declare(strict_types=1);

namespace Cpx\Commands;

use Cpx\Cache\Metadata;
use Cpx\Cache\PackageMetadata;
use Cpx\Support\Filesystem;
use Laravel\Prompts\Elements\Element;
use Laravel\Prompts\Support\Logger;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\callout;
use function Laravel\Prompts\error;
use function Laravel\Prompts\number;
use function Laravel\Prompts\select;
use function Laravel\Prompts\task;

#[AsCommand(
    name: 'clean',
    description: 'Clean unused cpx package caches',
)]
class CleanCommand extends Command
{
    private const SECONDS_PER_DAY = 24 * 60 * 60;

    private const DEFAULT_DAYS = 30;

    protected function configure(): void
    {
        $this->addOption('all', null, InputOption::VALUE_NONE, 'Clean all cached packages and sandboxes');
        $this->addOption('sandbox', null, InputOption::VALUE_NONE, 'Clean only sandbox (exec) caches');
        $this->addOption('days', null, InputOption::VALUE_REQUIRED, 'Clean packages older than this number of days');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $days = $input->getOption('days');

        if ($days !== null) {
            if (! is_numeric($days)) {
                return $this->rejectInvalidDays();
            }

            $days = (int) $days;

            if ($days < 0) {
                return $this->rejectInvalidDays();
            }
        }

        [$mode, $timeLimit] = $this->resolve($input, $days);

        $result = task(
            label: 'Cleaning cpx caches',
            callback: fn (Logger $logger): CleanResult => Metadata::transaction(
                fn (Metadata $metadata): CleanResult => $this->clean($metadata, $mode, $timeLimit, $logger),
            ),
        );

        $this->renderSummary($result);

        return self::SUCCESS;
    }

    /**
     * @return array{0: CleanMode, 1: int}
     */
    private function resolve(InputInterface $input, ?int $days): array
    {
        return match (true) {
            $input->getOption('all') === true => [CleanMode::All, $this->timeLimitForDays(self::DEFAULT_DAYS)],
            $input->getOption('sandbox') === true => [CleanMode::Sandbox, $this->timeLimitForDays(self::DEFAULT_DAYS)],
            $days !== null => [CleanMode::Period, $this->timeLimitForDays($days)],
            default => $this->promptForMode(),
        };
    }

    /**
     * @return array{0: CleanMode, 1: int}
     */
    private function promptForMode(): array
    {
        $choice = select(
            label: 'What would you like to clean?',
            options: [
                'all' => 'All cached packages and sandboxes',
                'sandbox' => 'Only sandbox (exec) caches',
                'period' => 'Packages older than a number of days',
            ],
            default: 'period',
        );

        return match ($choice) {
            'all' => [CleanMode::All, $this->timeLimitForDays(self::DEFAULT_DAYS)],
            'sandbox' => [CleanMode::Sandbox, $this->timeLimitForDays(self::DEFAULT_DAYS)],
            default => [CleanMode::Period, $this->timeLimitForDays($this->promptForDays())],
        };
    }

    private function promptForDays(): int
    {
        return (int) number(
            label: 'Remove packages older than how many days?',
            default: (string) self::DEFAULT_DAYS,
            min: 1,
        );
    }

    private function clean(Metadata $metadata, CleanMode $mode, int $timeLimit, Logger $logger): CleanResult
    {
        $result = new CleanResult;

        if ($mode->cleansPackages()) {
            $this->removeStalePackages($metadata, $mode->removesAllPackages(), $timeLimit, $result, $logger);
            $this->removeOrphanPackages($metadata, $result, $logger);
        }

        $this->removeStaleSandboxes($metadata, $mode->removesAllSandboxes(), $timeLimit, $result, $logger);
        $this->removeOrphanSandboxes($metadata, $result, $logger);

        return $result;
    }

    private function removeStalePackages(Metadata $metadata, bool $removeAll, int $timeLimit, CleanResult $result, Logger $logger): void
    {
        foreach ($metadata->packages as $key => $packageMetadata) {
            if (! $removeAll && ! $this->isStale($packageMetadata, $timeLimit)) {
                continue;
            }

            $description = "package {$packageMetadata->package->fullPackageString()}";

            if ($this->remove($packageMetadata->installPath(), $description, $result, $logger)) {
                unset($metadata->packages[$key]);
            }
        }
    }

    private function removeStaleSandboxes(Metadata $metadata, bool $removeAll, int $timeLimit, CleanResult $result, Logger $logger): void
    {
        foreach ($metadata->execCache as $key => $sandbox) {
            if (! $removeAll && ($sandbox->lastRunAt ?? 0) >= $timeLimit) {
                continue;
            }

            if ($this->remove(cpx_path(".exec_cache/{$key}"), "exec sandbox {$key}", $result, $logger)) {
                unset($metadata->execCache[$key]);
            }
        }
    }

    private function removeOrphanPackages(Metadata $metadata, CleanResult $result, Logger $logger): void
    {
        $tracked = [];

        foreach ($metadata->packages as $key => $packageMetadata) {
            $tracked[$packageMetadata->installPath()] = $key;
        }

        foreach (glob(cpx_path('*/*/*'), GLOB_ONLYDIR) ?: [] as $directory) {
            $trackedKey = $tracked[$directory] ?? null;

            if ($trackedKey !== null && file_exists("{$directory}/vendor/autoload.php")) {
                continue;
            }

            $description = 'orphaned package '.str_replace(cpx_path(), '', $directory);

            if (! $this->remove($directory, $description, $result, $logger)) {
                continue;
            }

            if ($trackedKey !== null) {
                unset($metadata->packages[$trackedKey]);
            }
        }
    }

    private function removeOrphanSandboxes(Metadata $metadata, CleanResult $result, Logger $logger): void
    {
        foreach (glob(cpx_path('.exec_cache/*'), GLOB_ONLYDIR) ?: [] as $directory) {
            if (array_key_exists(basename($directory), $metadata->execCache)) {
                continue;
            }

            $this->remove($directory, 'orphaned exec sandbox '.basename($directory), $result, $logger);
        }
    }

    private function isStale(PackageMetadata $packageMetadata, int $timeLimit): bool
    {
        $lastActivity = $packageMetadata->lastRunAt ?? $packageMetadata->lastUpdatedAt;

        if ($lastActivity === null) {
            return true;
        }

        $timestamp = strtotime($lastActivity);

        return $timestamp === false || $timestamp < $timeLimit;
    }

    private function remove(string $path, string $description, CleanResult $result, Logger $logger): bool
    {
        $logger->line("Removing {$description}...");

        try {
            Filesystem::deleteDirectoryWithin($path, cpx_path());

            $result->recordRemoval($description);

            return true;
        } catch (RuntimeException $exception) {
            $logger->warning($exception->getMessage());
            $result->recordFailure($exception->getMessage());

            return false;
        }
    }

    private function renderSummary(CleanResult $result): void
    {
        if ($result->isEmpty()) {
            callout(label: 'Clean Summary', content: 'Nothing to clean.');

            return;
        }

        $content = [];

        if ($result->removed !== []) {
            $content[] = Element::heading('Removed');
            $content[] = Element::bulletedList($result->removed);
        }

        if ($result->failures !== []) {
            $content[] = Element::heading('Could not remove');
            $content[] = Element::bulletedList($result->failures);
        }

        callout(
            label: 'Clean Summary',
            content: $content,
            type: $result->hasFailures() ? 'warning' : null,
        );
    }

    private function timeLimitForDays(int $days): int
    {
        return time() - ($days * self::SECONDS_PER_DAY);
    }

    private function rejectInvalidDays(): int
    {
        error('The --days option must be a positive integer.');

        return self::INVALID;
    }
}
