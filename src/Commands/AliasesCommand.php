<?php

declare(strict_types=1);

namespace Cpx\Commands;

use Cpx\PackageAliases;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'aliases',
    description: 'Show aliased package commands',
)]
class AliasesCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Aliased packages:'.PHP_EOL);
        $packages = PackageAliases::$packages;
        usort($packages, fn (array $a, array $b): int => strcmp($a['command'], $b['command']));

        foreach ($packages as $package) {
            $paddedCommand = str_pad($package['command'], 15);
            $output->writeln('  <info>cpx '.$paddedCommand.'</info>   '.$package['description']);
        }

        return self::SUCCESS;
    }
}
