<?php

declare(strict_types=1);

namespace Cpx\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\HelpCommand as SymfonyHelpCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'help',
    description: 'Show the cpx help message',
)]
class HelpCommand extends SymfonyHelpCommand
{
    private ?Command $commandForHelp = null;

    protected function configure(): void
    {
        parent::configure();

        $this->setDescription('Show the cpx help message');
    }

    public function setCommand(Command $command): void
    {
        $this->commandForHelp = $command;

        parent::setCommand($command);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($this->commandForHelp !== null) {
            try {
                return parent::execute($input, $output);
            } finally {
                $this->commandForHelp = null;
            }
        }

        if ($input->getArgument('command_name') !== 'help') {
            return parent::execute($input, $output);
        }

        self::render($output);

        return self::SUCCESS;
    }

    public static function render(OutputInterface $output, ?string $unknownCommand = null): void
    {
        if ($unknownCommand !== null) {
            $output->writeln("<error>Unrecognised command {$unknownCommand}</error>");
        }

        $output->writeln('<info>cpx - A Composer package runner with on-demand execution and package management.</info>');
        $output->writeln('Usage:');
        $output->writeln('  <info>cpx <vendor/package[:version]> [args]   </info>Run a Composer package\'s bin command');
        $output->writeln('  <info>cpx check                               </info>Run a static analysis tool over a project');
        $output->writeln('  <info>cpx test                                </info>Run a testing framework over a project');
        $output->writeln('  <info>cpx format                              </info>Run a code formatter over a project');
        $output->writeln('  <info>cpx update                              </info>Update all packages');
        $output->writeln('  <info>cpx update <vendor/package>             </info>Update all versions of a package');
        $output->writeln('  <info>cpx clean                               </info>Clean unused packages (older than 30 days)');
        $output->writeln('  <info>cpx clean --all                         </info>Clean all packages');
        $output->writeln('  <info>cpx exec </path/to/php/file.php>        </info>Invoke a PHP file');
        $output->writeln('  <info>cpx exec -r <code>                      </info>Run PHP code without <?php ?> tags');
        $output->writeln('  <info>cpx tinker                              </info>Open an interactive REPL');
        $output->writeln('  <info>cpx list                                </info>List all installed packages');
        $output->writeln('  <info>cpx aliases                             </info>Show aliased package names to run via `cpx <alias>`');
        $output->writeln('  <info>cpx help                                </info>Show this help message');
    }
}
