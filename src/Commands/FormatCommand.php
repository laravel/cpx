<?php

declare(strict_types=1);

namespace Cpx\Commands;

use Cpx\Console;
use Cpx\Exceptions\ConsoleException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'format',
    description: 'Run a code formatter over a project',
    aliases: ['fmt'],
)]
class FormatCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('directory', InputArgument::OPTIONAL, 'Directory to format', '.');
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Report formatting changes without writing them');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $directory = $input->getArgument('directory');
        $directory = is_string($directory) ? $directory : '.';

        if (file_exists('vendor/bin/pint')) {
            $command = "vendor/bin/pint {$directory}";

            if ($input->getOption('dry-run') === true) {
                $command .= ' --test';
            }

            Console::parse($command)->exec();

            return self::SUCCESS;
        }

        if (file_exists('vendor/bin/php-cs-fixer')) {
            $command = "vendor/bin/php-cs-fixer fix {$directory}";

            if ($input->getOption('dry-run') === true) {
                $command .= ' --dry-run';
            }

            $command .= ' --allow-risky=yes';

            Console::parse($command)->exec();

            return self::SUCCESS;
        }

        if (file_exists('vendor/bin/phpcbf')) {
            $command = "vendor/bin/phpcbf {$directory}";

            Console::parse($command)->exec();

            return self::SUCCESS;
        }

        throw new ConsoleException('No code formatters found in the project.');
    }
}
