<?php

namespace Cpx\Commands;

use Cpx\Console;
use Cpx\Exceptions\ConsoleException;

class TestCommand extends Command
{
    public function __invoke()
    {
        if (file_exists('vendor/bin/pest')) {
            return Console::parse('vendor/bin/pest')->exec();
        }

        if (file_exists('bin/phpunit')) {
            return Console::parse('bin/phpunit')->exec();
        }

        if (file_exists('vendor/bin/phpunit')) {
            return Console::parse('vendor/bin/phpunit')->exec();
        }

        if (file_exists('vendor/bin/codecept')) {
            return Console::parse('vendor/bin/codecept')->exec();
        }

        throw new ConsoleException('No test runner found in the project.');
    }
}
