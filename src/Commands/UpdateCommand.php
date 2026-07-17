<?php

declare(strict_types=1);

namespace Cpx\Commands;

use Cpx\Commands\Concerns\OutputsJson;
use Cpx\Composer\ComposerRunner;
use Cpx\Exceptions\ComposerCommandException;
use Cpx\Packages\Package;
use Cpx\Process\ProcessRunner;
use Cpx\Support\Filesystem;
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

        match (true) {
            str_contains($target, '/') => $this->updatePackage(Package::parse($target)),
            $target !== '' => $this->updateVendor($target),
            default => $this->updateAllPackages(),
        };

        if ($this->json) {
            return $this->errors === []
                ? $this->outputJsonSuccess($output, ['packages' => $this->packages])
                : $this->outputJsonFailure($output, $this->errors, ['packages' => $this->packages]);
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
            $previousVersion = ComposerRunner::getCurrentVersion($directory, $package);
            $error = null;

            try {
                ProcessRunner::withLogger(new Logger('cpx'), fn () => ComposerRunner::run(['update'], $directory));
            } catch (ComposerCommandException $exception) {
                $error = $exception->getMessage();
                $this->errors[] = "{$relative}: {$error}";
            }

            $newVersion = ComposerRunner::getCurrentVersion($directory, $package);
            $updated = $error === null && $previousVersion !== $newVersion;

            $this->packages[] = [
                'package' => $relative,
                'updated' => $updated,
                'from' => $previousVersion,
                'to' => $newVersion,
                'reason' => $error ?? ($updated ? null : 'already up-to-date'),
            ];

            return;
        }

        task(
            label: "Updating {$relative}",
            callback: function (Logger $logger) use ($directory, $relative, $package): void {
                $previousVersion = ComposerRunner::getCurrentVersion($directory, $package);

                try {
                    ProcessRunner::withLogger($logger, fn () => ComposerRunner::run(['update'], $directory));
                } catch (ComposerCommandException $exception) {
                    $this->errors[] = "{$relative}: {$exception->getMessage()}";
                    $logger->error($exception->getMessage());
                    $logger->label("{$relative} could not be updated");

                    return;
                }

                $newVersion = ComposerRunner::getCurrentVersion($directory, $package);

                if ($previousVersion !== $newVersion) {
                    $logger->label("{$relative} was upgraded from {$previousVersion} to {$newVersion}");
                } else {
                    $logger->label("{$relative} is already up-to-date");
                }
            },
            keepSummary: true,
        );
    }
}
