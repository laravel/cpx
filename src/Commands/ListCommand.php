<?php

declare(strict_types=1);

namespace Cpx\Commands;

use Cpx\Cache\Metadata;
use Cpx\Cache\PackageMetadata;
use Laravel\Prompts\Elements\Element;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\callout;
use function Laravel\Prompts\info;

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

        ksort($metadata->packages);

        callout('Installed Packages:', [
            Element::keyValueList(array_combine(
                array_map(
                    fn (PackageMetadata $packageMetadata): string => $packageMetadata->package->fullPackageString(),
                    $metadata->packages,
                ),
                array_map(
                    fn (PackageMetadata $packageMetadata): string => 'Last Run: '.$packageMetadata->lastRunForDisplay(),
                    $metadata->packages,
                ),
            )),
        ]);

        return self::SUCCESS;
    }
}
