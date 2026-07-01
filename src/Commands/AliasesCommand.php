<?php

declare(strict_types=1);

namespace Cpx\Commands;

use Cpx\Packages\Package;
use Cpx\Packages\PackageAlias;
use Cpx\Packages\PackageAliases;
use Cpx\Packages\UserAliases;
use Laravel\Prompts\Prompt;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\info;
use function Laravel\Prompts\table;

#[AsCommand(
    name: 'aliases',
    description: 'Show aliased package commands',
)]
class AliasesCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        Prompt::setOutput($output);

        $output->writeln('Aliased packages:'.PHP_EOL);
        $packages = PackageAliases::all();
        usort($packages, fn (PackageAlias $a, PackageAlias $b): int => strcmp($a->command, $b->command));

        foreach ($packages as $package) {
            $paddedCommand = str_pad($package->command, 15);
            $output->writeln('  <info>cpx '.$paddedCommand.'</info>   '.$package->description);
        }

        $userAliases = UserAliases::open()->all();

        if ($userAliases !== []) {
            ksort($userAliases);

            info('Your aliases:');

            table(
                headers: ['Alias', 'Package'],
                rows: array_map(
                    fn (string $name, Package $package): array => ['cpx '.$name, $package->fullPackageString()],
                    array_keys($userAliases),
                    $userAliases,
                ),
            );
        }

        return self::SUCCESS;
    }
}
