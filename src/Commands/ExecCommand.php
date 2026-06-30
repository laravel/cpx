<?php

declare(strict_types=1);

namespace Cpx\Commands;

use Cpx\Runtime\PhpExecutionHelper;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'exec',
    description: 'Invoke a PHP file or inline PHP code',
)]
class ExecCommand extends Command
{
    private string $path;

    protected function configure(): void
    {
        $this->addArgument('file', InputArgument::OPTIONAL, 'PHP file to invoke');
        $this->addOption('run', 'r', InputOption::VALUE_REQUIRED, 'Run PHP code without <?php ?> tags');
        $this->addOption('find-autoloader', null, InputOption::VALUE_NEGATABLE, 'Find and load the nearest Composer autoloader', true);
        $this->addOption('load-laravel-bootstrap', null, InputOption::VALUE_NEGATABLE, 'Load Laravel bootstrap files when available', true);
        $this->addOption('alias-classes', null, InputOption::VALUE_NEGATABLE, 'Alias classes from loaded autoloaders', true);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        ob_start();

        try {
            return $this->executeInput($input, $output);
        } finally {
            $contents = ob_get_clean();

            if ($contents !== false) {
                $output->write($contents);
            }
        }
    }

    protected function autoload(string $directory, InputInterface $input, OutputInterface $output): void
    {
        $shouldFindAutoloader = $input->getOption('find-autoloader') === true;
        $shouldLoadLaravelBootstrap = $input->getOption('load-laravel-bootstrap') === true;
        $shouldAliasClasses = $input->getOption('alias-classes') === true;
        $shouldBeVerbose = $output->isVerbose();

        PhpExecutionHelper::init($directory, $shouldFindAutoloader, $shouldLoadLaravelBootstrap, $shouldAliasClasses, $shouldBeVerbose);
    }

    private function executeInput(InputInterface $input, OutputInterface $output): int
    {
        if ($input->getOption('run') !== null) {
            $code = $input->getOption('run');

            if (! is_string($code) || $code === '') {
                $output->writeln('<error>Please supply code to execute with the -r option.</error>');

                return self::FAILURE;
            }

            if (str_starts_with($code, '<?php')) {
                $code = substr($code, 5);

                if (str_ends_with(trim($code), '?>')) {
                    $code = substr($code, 0, -2);
                }
            }

            if (! str_ends_with(trim($code), ';')) {
                $code .= ';';
            }

            $directory = getcwd();

            if ($directory === false) {
                $output->writeln('<error>Unable to determine the current working directory.</error>');

                return self::FAILURE;
            }

            $this->autoload($directory, $input, $output);

            eval($code);
            echo PHP_EOL;

            return self::SUCCESS;
        }

        $file = $input->getArgument('file');

        if (! is_string($file) || $file === '') {
            $output->writeln('<error>Please supply the path to a file to execute.</error>');

            return self::FAILURE;
        }

        $path = realpath($file);

        if ($path === false || ! file_exists($path)) {
            $output->writeln("<error>File does not exist at '{$file}'</error>");

            return self::FAILURE;
        }

        $this->path = $path;

        $this->autoload(dirname($this->path), $input, $output);

        $this->runFile();

        return self::SUCCESS;
    }

    private function runFile(): void
    {
        require $this->path;
    }
}
