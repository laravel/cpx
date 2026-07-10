<?php

declare(strict_types=1);

namespace Cpx\Commands;

use Cpx\Packages\ExecSandbox;
use Cpx\Runtime\ComposerRequire;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: ComposerRequire::COMMAND,
    hidden: true,
)]
class SandboxCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('packages', InputArgument::IS_ARRAY | InputArgument::REQUIRED, 'Packages to install into the exec sandbox');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var list<string> $packages */
        $packages = $input->getArgument('packages');

        $output->writeln(ExecSandbox::forPackages($packages)->ensureInstalled());

        return self::SUCCESS;
    }
}
