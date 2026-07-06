<?php

declare(strict_types=1);

namespace Cpx\Commands;

use Cpx\Packages\UserAliases;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

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

        $output->writeln('Your aliases:'.PHP_EOL);

        foreach ($userAliases as $name => $package) {
            $paddedCommand = str_pad($name, 15);
            $output->writeln('  <info>cpx '.$paddedCommand.'</info>   '.$package->displayString());
        }

        return self::SUCCESS;
    }
}
