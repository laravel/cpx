<?php

declare(strict_types=1);

namespace Cpx\Commands;

use Cpx\Cache\Metadata;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

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
            $output->writeln('There are no installed packages.');

            return self::SUCCESS;
        }

        $output->writeln('Installed Packages:');

        foreach ($metadata->packages as $packageMetadata) {
            $output->writeln("<info>  {$packageMetadata->package->fullPackageString()}</info> (Last Run: {$packageMetadata->lastRunForDisplay()})");
        }

        return self::SUCCESS;
    }
}
