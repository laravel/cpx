<?php

declare(strict_types=1);

namespace Cpx\Commands;

use Cpx\Packages\Package;
use Cpx\Packages\PackageAlias;
use Cpx\Packages\PackageAliases;
use Cpx\Packages\UserAliases;
use Laravel\Prompts\Elements\Element;
use Laravel\Prompts\Prompt;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\callout;

#[AsCommand(
    name: 'aliases',
    description: 'Show aliased package commands',
)]
class AliasesCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        Prompt::setOutput($output);

        $userAliases = UserAliases::open()->all();

        $packages = array_filter(
            PackageAliases::all(),
            fn (PackageAlias $package): bool => ! array_key_exists($package->command, $userAliases),
        );
        usort($packages, fn (PackageAlias $a, PackageAlias $b): int => strcmp($a->command, $b->command));

        if ($packages !== []) {
            callout('Aliased packages:', [
                Element::keyValueList(array_combine(
                    array_map(fn (PackageAlias $package): string => 'cpx '.$package->command, $packages),
                    array_map(fn (PackageAlias $package): string => $package->package, $packages),
                )),
            ]);
        }

        if ($userAliases !== []) {
            ksort($userAliases);

            callout('Your aliases:', [
                Element::keyValueList(array_combine(
                    array_map(fn (string $name): string => 'cpx '.$name, array_keys($userAliases)),
                    array_map(fn (Package $package): string => $package->fullPackageString(), $userAliases),
                )),
            ]);
        }

        return self::SUCCESS;
    }
}
