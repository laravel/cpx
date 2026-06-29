<?php

declare(strict_types=1);

namespace Cpx\Commands;

use Cpx\Console;
use Cpx\PackageCommandRunner;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: self::Name,
    hidden: true,
)]
class RunPackageCommand extends SymfonyCommand
{
    public const Name = '__cpx_run_package';

    public function __construct(
        private PackageCommandRunner $packageCommandRunner,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        // Let undeclared package args/options pass through instead of failing validation;
        // execute() reads the raw tokens and forwards them to the package.
        $this->ignoreValidationErrors();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $console = Console::parse($input instanceof ArgvInput ? $input->getRawTokens() : []);

        ob_start();

        try {
            return $this->packageCommandRunner->run($console, $output);
        } finally {
            $contents = ob_get_clean();

            if ($contents !== false) {
                $output->write($contents);
            }
        }
    }
}
