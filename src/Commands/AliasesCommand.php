<?php

declare(strict_types=1);

namespace Cpx\Commands;

use Cpx\Packages\Package;
use Cpx\Packages\UserAliases;
use Laravel\Prompts\Elements\Element;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\callout;

#[AsCommand(
    name: 'aliases',
    description: 'Show your package aliases',
)]
class AliasesCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $userAliases = UserAliases::open()->all();

        if ($userAliases === []) {
            $output->writeln('You have no aliases. Create one with <info>cpx alias</info>.');

            return self::SUCCESS;
        }

        ksort($userAliases);

        callout('Your aliases:', [
            Element::keyValueList(array_combine(
                array_map(fn (string $name): string => 'cpx '.$name, array_keys($userAliases)),
                array_map(fn (Package $package): string => $package->displayString(), $userAliases),
            )),
        ]);

        return self::SUCCESS;
    }
}
