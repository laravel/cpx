<?php

declare(strict_types=1);

namespace Cpx\Commands;

use Cpx\Input\PackageInvocation;
use Cpx\Packages\Package;
use Cpx\Support\ChildScript;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'tinker',
    description: 'Open an interactive REPL',
)]
class TinkerCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return Package::parse('psy/psysh')->runCommand(
            PackageInvocation::fromRawTokens(['psysh', '--config', ChildScript::path('psysh-config.php')]),
        );
    }
}
