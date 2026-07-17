<?php

declare(strict_types=1);

namespace Cpx\Commands;

use Cpx\Cache\Metadata;
use Cpx\Cache\PackageMetadata;
use Cpx\Commands\Concerns\OutputsJson;
use Laravel\Prompts\Elements\Element;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\callout;
use function Laravel\Prompts\info;

#[AsCommand(
    name: 'installed',
    description: 'List installed cpx packages',
)]
class InstalledCommand extends Command
{
    use OutputsJson;

    protected function configure(): void
    {
        $this->addJsonOption();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $metadata = Metadata::open();

        ksort($metadata->packages);

        if ($this->wantsJson($input)) {
            return $this->outputJsonSuccess($output, [
                'packages' => array_map(
                    fn (PackageMetadata $packageMetadata): array => [
                        'name' => $packageMetadata->package->fullPackageString(),
                        'last_run' => $packageMetadata->lastRun(),
                    ],
                    array_values($metadata->packages),
                ),
            ]);
        }

        if (empty($metadata->packages)) {
            info('There are no installed packages.');

            return self::SUCCESS;
        }

        callout('Installed Packages:', [
            Element::keyValueList(array_combine(
                array_map(
                    fn (PackageMetadata $packageMetadata): string => $packageMetadata->package->fullPackageString(),
                    $metadata->packages,
                ),
                array_map(
                    fn (PackageMetadata $packageMetadata): string => 'Last Run: '.($packageMetadata->lastRun() ?? 'N/A'),
                    $metadata->packages,
                ),
            )),
        ]);

        return self::SUCCESS;
    }
}
