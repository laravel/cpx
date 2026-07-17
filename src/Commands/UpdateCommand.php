<?php

declare(strict_types=1);

namespace Cpx\Commands;

use Cpx\Commands\Concerns\OutputsJson;
use Cpx\Composer\ComposerRunner;
use Cpx\Exceptions\ComposerCommandException;
use Cpx\Packages\Package;
use Cpx\Process\ProcessRunner;
use Cpx\Support\Filesystem;
use Cpx\Support\Result;
use Cpx\Support\SilentLogger;
use InvalidArgumentException;
use Laravel\Prompts\Elements\Element;
use Laravel\Prompts\Support\Logger;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\callout;
use function Laravel\Prompts\info;
use function Laravel\Prompts\task;

#[AsCommand(
    name: 'update',
    description: 'Update installed cpx packages',
)]
class UpdateCommand extends Command
{
    use OutputsJson;

    private bool $json = false;

    /** @var list<array{package: string, updated: bool, from: string, to: string, reason: string|null}> */
    private array $packages = [];

    /** @var list<string> */
    private array $errors = [];

    protected function configure(): void
    {
        $this->addArgument('target', InputArgument::OPTIONAL, 'Package or vendor to update');
        $this->addJsonOption();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->json = $this->wantsJson($input);
        $this->packages = [];
        $this->errors = [];

        $target = (string) $input->getArgument('target');

        try {
            match (true) {
                str_contains($target, '/') => $this->updatePackage(Package::parse($target)),
                $target !== '' => $this->updateVendor($target),
                default => $this->updateAllPackages(),
            };
        } catch (InvalidArgumentException $exception) {
            return Result::failure($output, $exception->getMessage());
        }

        if ($this->json) {
            return $this->errors === []
                ? Result::success($output, ['packages' => $this->packages])
                : Result::failure($output, $this->errors, ['packages' => $this->packages]);
        }

        if ($this->errors === []) {
            return self::SUCCESS;
        }

        callout(
            label: 'Update Summary',
            content: [
                Element::heading('Could not update'),
                Element::bulletedList($this->errors),
            ],
            type: 'warning',
        );

        return self::FAILURE;
    }

    protected function updateAllPackages(): void
    {
        $packageDirectories = glob(cpx_path('*/*/*'), GLOB_ONLYDIR) ?: [];

        if (empty($packageDirectories)) {
            if (! $this->json) {
                info('There are no packages to update.');
            }
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
            if (! $this->json) {
                info("There are no packages in vendor '{$vendor}' to update.");
            }
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
            if (! $this->json) {
                info("There are no installed versions of '{$package->vendor}/{$package->name}' to update.");
            }
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

        if ($this->json) {
            $this->record($relative, $this->attemptUpdate($directory, $package, new SilentLogger));

            return;
        }

        task(
            label: "Updating {$relative}",
            callback: function (Logger $logger) use ($directory, $relative, $package): void {
                $outcome = $this->attemptUpdate($directory, $package, $logger);
                $this->record($relative, $outcome);

                if ($outcome['error'] !== null) {
                    $logger->error($outcome['error']);
                }

                $logger->label(match (true) {
                    $outcome['error'] !== null => "{$relative} could not be updated",
                    $outcome['from'] !== $outcome['to'] => "{$relative} was upgraded from {$outcome['from']} to {$outcome['to']}",
                    default => "{$relative} is already up-to-date",
                });
            },
            keepSummary: true,
        );
    }

    /** @return array{from: string, to: string, error: string|null} */
    private function attemptUpdate(string $directory, string $package, Logger $logger): array
    {
        $from = ComposerRunner::getCurrentVersion($directory, $package);
        $error = null;

        try {
            ProcessRunner::withLogger($logger, fn () => ComposerRunner::run(['update'], $directory));
        } catch (ComposerCommandException $exception) {
            $error = $exception->getMessage();
        }

        return [
            'from' => $from,
            'to' => ComposerRunner::getCurrentVersion($directory, $package),
            'error' => $error,
        ];
    }

    /** @param array{from: string, to: string, error: string|null} $outcome */
    private function record(string $relative, array $outcome): void
    {
        $updated = $outcome['error'] === null && $outcome['from'] !== $outcome['to'];

        if ($outcome['error'] !== null) {
            $this->errors[] = "{$relative}: {$outcome['error']}";
        }

        $this->packages[] = [
            'package' => $relative,
            'updated' => $updated,
            'from' => $outcome['from'],
            'to' => $outcome['to'],
            'reason' => $outcome['error'] ?? ($updated ? null : 'already up-to-date'),
        ];
    }
}
