<?php

declare(strict_types=1);

namespace Cpx\Commands;

use Cpx\Console;
use Cpx\Package;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'tinker',
    description: 'Open an interactive REPL',
)]
class TinkerCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $psyshConfig = realpath(__DIR__.'/../../files/psysh-config.php');

        if ($psyshConfig === false) {
            $output->writeln('<error>Unable to find the PsySH configuration file.</error>');

            return self::FAILURE;
        }

        ob_start();

        try {
            Package::parse('psy/psysh')->runCommand(Console::parse("psysh --config {$psyshConfig}"));
        } finally {
            $contents = ob_get_clean();

            if ($contents !== false) {
                $output->write($contents);
            }
        }

        return self::SUCCESS;
    }
}
