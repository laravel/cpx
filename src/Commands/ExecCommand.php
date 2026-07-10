<?php

declare(strict_types=1);

namespace Cpx\Commands;

use Cpx\Process\ProcessRunner;
use Cpx\Runtime\ExecEnvironment;
use Cpx\Support\ChildScript;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\error;

#[AsCommand(
    name: 'exec',
    description: 'Invoke a PHP file or inline PHP code',
)]
class ExecCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('file', InputArgument::OPTIONAL, 'PHP file to invoke');
        $this->addOption('run', 'r', InputOption::VALUE_REQUIRED, 'Run PHP code without <?php ?> tags');
        $this->addOption('find-autoloader', null, InputOption::VALUE_NEGATABLE, 'Find and load the nearest Composer autoloader', true);
        $this->addOption('boot', null, InputOption::VALUE_NEGATABLE, 'Boot the detected framework when available', true);
        $this->addOption('alias-classes', null, InputOption::VALUE_NEGATABLE, 'Alias classes from loaded autoloaders', true);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $code = null;
        $file = null;

        if ($input->getOption('run') !== null) {
            $run = $input->getOption('run');

            if (! is_string($run) || $run === '') {
                error('Please supply code to execute with the -r option.');

                return self::FAILURE;
            }

            $code = $this->normalizeCode($run);
        } else {
            $target = $input->getArgument('file');

            if (! is_string($target) || $target === '') {
                error('Please supply the path to a file to execute.');

                return self::FAILURE;
            }

            $file = realpath($target);

            if ($file === false) {
                error("File does not exist at '{$target}'");

                return self::FAILURE;
            }
        }

        $environment = new ExecEnvironment(
            file: $file,
            code: $code,
            findAutoloader: $input->getOption('find-autoloader') === true,
            boot: $input->getOption('boot') === true,
            aliasClasses: $input->getOption('alias-classes') === true,
            verbose: $output->isVerbose(),
        );

        return (new ProcessRunner)->run(
            [PHP_BINARY, ChildScript::path('exec-bootstrap.php')],
            $environment->toEnvironment(),
        );
    }

    private function normalizeCode(string $code): string
    {
        if (str_starts_with($code, '<?php')) {
            $code = substr($code, 5);

            if (str_ends_with(trim($code), '?>')) {
                $code = substr(rtrim($code), 0, -2);
            }
        }

        if (! str_ends_with(trim($code), ';')) {
            $code .= ';';
        }

        return $code;
    }
}
