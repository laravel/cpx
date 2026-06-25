<?php

declare(strict_types=1);

namespace Cpx;

use Cpx\Commands\AliasesCommand;
use Cpx\Commands\CheckCommand;
use Cpx\Commands\CleanCommand;
use Cpx\Commands\ExecCommand;
use Cpx\Commands\FormatCommand;
use Cpx\Commands\HelpCommand;
use Cpx\Commands\ListCommand;
use Cpx\Commands\RunPackageCommand;
use Cpx\Commands\TestCommand;
use Cpx\Commands\TinkerCommand;
use Cpx\Commands\UpdateCommand;
use Cpx\Commands\UpgradeCommand;
use Cpx\Commands\VersionCommand;
use Symfony\Component\Console\Application as SymfonyApplication;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Application extends SymfonyApplication
{
    public function __construct(?PackageCommandRunner $packageCommandRunner = null)
    {
        parent::__construct('cpx');
        $this->setAutoExit(false);

        $this->registerCommands($packageCommandRunner ?? new PackageCommandRunner);
    }

    public function run(?InputInterface $input = null, ?OutputInterface $output = null): int
    {
        $input ??= new ArgvInput;

        if ($input instanceof ArgvInput && $this->isVersionRequest($input)) {
            $input = new ArgvInput(['cpx', 'version']);
        }

        return parent::run($input, $output);
    }

    protected function getCommandName(InputInterface $input): ?string
    {
        if ($this->isEmptyRequest($input)) {
            return 'help';
        }

        if ($this->isVersionRequest($input)) {
            return 'version';
        }

        $command = parent::getCommandName($input);

        return $command === null || $this->has($command)
            ? $command
            : RunPackageCommand::Name;
    }

    private function registerCommands(PackageCommandRunner $packageCommandRunner): void
    {
        $this->addCommands([
            new HelpCommand,
            new ListCommand,
            new AliasesCommand,
            new CleanCommand,
            new UpdateCommand,
            new UpgradeCommand,
            new ExecCommand,
            new FormatCommand,
            new CheckCommand,
            new TestCommand,
            new TinkerCommand,
            new VersionCommand,
            new RunPackageCommand($packageCommandRunner),
        ]);
    }

    private function isVersionRequest(InputInterface $input): bool
    {
        return ! $input instanceof ArgvInput
            ? false
            : in_array($input->getRawTokens()[0] ?? null, ['--version', '-v'], true);
    }

    private function isEmptyRequest(InputInterface $input): bool
    {
        return $input instanceof ArgvInput && $input->getRawTokens() === [];
    }
}
