<?php

declare(strict_types=1);

namespace Cpx\Commands;

use Cpx\Composer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'upgrade',
    description: 'Upgrade cpx itself',
)]
class UpgradeCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Updating <info>cpx</info>');
        Composer::runCommand('global update cpx/cpx');

        return self::SUCCESS;
    }
}
