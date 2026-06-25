<?php

declare(strict_types=1);

namespace Cpx\Commands;

use Cpx\Console;
use Cpx\Exceptions\ConsoleException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'check',
    description: 'Run a static analysis tool over a project',
    aliases: ['analyze', 'analyse'],
)]
class CheckCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (file_exists('vendor/bin/phpstan')) {
            $command = 'vendor/bin/phpstan analyse';

            Console::parse($command)->exec();

            return self::SUCCESS;
        }

        if (file_exists('vendor/bin/psalm')) {
            $command = 'vendor/bin/psalm';

            Console::parse($command)->exec();

            return self::SUCCESS;
        }

        if (file_exists('vendor/bin/phan')) {
            $command = 'vendor/bin/phan';

            Console::parse($command)->exec();

            return self::SUCCESS;
        }

        throw new ConsoleException('No static analyzers found in the project.');
    }
}
