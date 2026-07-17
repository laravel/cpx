<?php

declare(strict_types=1);

namespace Cpx\Commands;

use Cpx\Cache\ExecSandboxMetadata;
use Cpx\Cache\Metadata;
use Cpx\Commands\Concerns\OutputsJson;
use Cpx\Support\Filesystem;
use InvalidArgumentException;
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
    use OutputsJson;

    private const SECONDS_PER_DAY = 24 * 60 * 60;

    private const DEFAULT_DAYS = 30;

    protected function configure(): void
    {
        $this->addOption('all', null, InputOption::VALUE_NONE, 'Clean all cached packages and sandboxes');
        $this->addOption('sandbox', null, InputOption::VALUE_NONE, 'Clean only sandbox (exec) caches');
        $this->addOption('days', null, InputOption::VALUE_REQUIRED, 'Clean packages older than this number of days');
        $this->addJsonOption();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $json = $this->wantsJson($input);

        try {
            $days = $this->resolveDays($input->getOption('days'));
        } catch (InvalidArgumentException) {
            if ($json) {
                return $this->outputJsonFailure($output, 'The --days option must be a positive integer.', status: self::INVALID);
            }

            return $this->rejectInvalidDays();
        }

        [$mode, $timeLimit] = $this->resolve($input, $days);

        if ($json) {
            $result = Metadata::transaction(
                fn (Metadata $metadata): CleanResult => $this->clean($metadata, $mode, $timeLimit, new Logger('cpx')),
            );

            return $result->hasFailures()
                ? $this->outputJsonFailure($output, $result->failures, ['removed' => $result->removed])
                : $this->outputJsonSuccess($output, ['removed' => $result->removed]);
        }

        $result = task(
            label: 'Cleaning cpx caches',
            callback: fn (Logger $logger): CleanResult => Metadata::transaction(
                fn (Metadata $metadata): CleanResult => $this->clean($metadata, $mode, $timeLimit, $logger),
            ),
        );

        $this->renderSummary($result);

        return $result->hasFailures() ? self::FAILURE : self::SUCCESS;
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

    private function resolveDays(mixed $days): ?int
    {
        if ($days === null) {
            return null;
        }

        if (! is_numeric($days)) {
            throw new InvalidArgumentException;
        }

        $days = (int) $days;

        if ($days < 0) {
            throw new InvalidArgumentException;
        }

        return $days;
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
            if (! $removeAll && ! $this->isStale($packageMetadata->lastRunAt, $packageMetadata->lastUpdatedAt, $timeLimit)) {
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
            if (! $removeAll && ! $this->isStale($sandbox->lastRunAt, $sandbox->lastUpdatedAt, $timeLimit)) {
                continue;
            }

            if ($this->remove($sandbox->path(), "exec sandbox {$key}", $result, $logger)) {
                unset($metadata->execCache[$key]);
            }
        }
    }

    private function removeOrphanPackages(Metadata $metadata, CleanResult $result, Logger $logger): void
    {
        $tracked = [];

        foreach ($metadata->packages as $key => $packageMetadata) {
            $tracked[Filesystem::normalizePath($packageMetadata->installPath())] = $key;
        }

        $cacheRoot = Filesystem::normalizePath(cpx_path());

        foreach (glob(cpx_path('*/*/*'), GLOB_ONLYDIR) ?: [] as $directory) {
            $normalizedDirectory = Filesystem::normalizePath($directory);
            $trackedKey = $tracked[$normalizedDirectory] ?? null;

            if ($trackedKey !== null && file_exists("{$directory}/vendor/autoload.php")) {
                continue;
            }

            $description = 'orphaned package '.str_replace($cacheRoot, '', $normalizedDirectory);

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
        foreach (glob(ExecSandboxMetadata::rootPath().'/*', GLOB_ONLYDIR) ?: [] as $directory) {
            if (array_key_exists(basename($directory), $metadata->execCache)) {
                continue;
            }

            $this->remove($directory, 'orphaned exec sandbox '.basename($directory), $result, $logger);
        }
    }

    private function isStale(?int $lastRunAt, ?int $lastUpdatedAt, int $timeLimit): bool
    {
        $lastActivity = $lastRunAt ?? $lastUpdatedAt;

        return $lastActivity === null || $lastActivity < $timeLimit;
    }

    private function remove(string $path, string $description, CleanResult $result, Logger $logger): bool
    {
        $logger->line("Removing {$description}...");

        try {
            Filesystem::deleteDirectoryWithin($path, cpx_path());
            Filesystem::pruneEmptyParents($path, cpx_path());

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
