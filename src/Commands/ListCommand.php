<?php

declare(strict_types=1);

namespace Cpx\Commands;

use Cpx\Cache\Metadata;
use Cpx\Cache\PackageMetadata;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\info;
use function Laravel\Prompts\table;

#[AsCommand(
    name: 'list',
    description: 'List installed cpx packages',
)]
class ListCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $metadata = Metadata::open();

        if (empty($metadata->packages)) {
            info('There are no installed packages.');

            return self::SUCCESS;
        }

        info('Installed Packages:');
        table(
            headers: ['Package', 'Details'],
            rows: array_values(array_map(
                fn (PackageMetadata $packageMetadata): array => [
                    $packageMetadata->package->fullPackageString(),
                    'Last Run: '.$packageMetadata->lastRunForDisplay(),
                ],
                $metadata->packages,
            )),
        );

        return self::SUCCESS;
    }
}
