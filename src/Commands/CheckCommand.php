<?php

declare(strict_types=1);

namespace Cpx\Commands;

use Cpx\Console;
use Cpx\Exceptions\ConsoleException;

class CheckCommand extends Command
{
    public function __invoke(): void
    {
        if (file_exists('vendor/bin/phpstan')) {
            $command = 'vendor/bin/phpstan analyse';

            Console::parse($command)->exec();

            return;
        }

        if (file_exists('vendor/bin/psalm')) {
            $command = 'vendor/bin/psalm';

            Console::parse($command)->exec();

            return;
        }

        if (file_exists('vendor/bin/phan')) {
            $command = 'vendor/bin/phan';

            Console::parse($command)->exec();

            return;
        }

        throw new ConsoleException('No static analyzers found in the project.');
    }
}
