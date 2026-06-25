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
    name: 'test',
    description: 'Run a testing framework over a project',
)]
class TestCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (file_exists('vendor/bin/pest')) {
            Console::parse('vendor/bin/pest')->exec();

            return self::SUCCESS;
        }

        if (file_exists('bin/phpunit')) {
            Console::parse('bin/phpunit')->exec();

            return self::SUCCESS;
        }

        if (file_exists('vendor/bin/phpunit')) {
            Console::parse('vendor/bin/phpunit')->exec();

            return self::SUCCESS;
        }

        if (file_exists('vendor/bin/codecept')) {
            Console::parse('vendor/bin/codecept')->exec();

            return self::SUCCESS;
        }

        throw new ConsoleException('No test runner found in the project.');
    }
}
