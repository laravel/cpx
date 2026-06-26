<?php

declare(strict_types=1);

namespace Cpx\Commands;

use Cpx\Console;
use Cpx\Exceptions\ConsoleException;

class TestCommand extends Command
{
    public function __invoke(): void
    {
        if (file_exists('vendor/bin/pest')) {
            Console::parse('vendor/bin/pest')->exec();

            return;
        }

        if (file_exists('bin/phpunit')) {
            Console::parse('bin/phpunit')->exec();

            return;
        }

        if (file_exists('vendor/bin/phpunit')) {
            Console::parse('vendor/bin/phpunit')->exec();

            return;
        }

        if (file_exists('vendor/bin/codecept')) {
            Console::parse('vendor/bin/codecept')->exec();

            return;
        }

        throw new ConsoleException('No test runner found in the project.');
    }
}
