<?php

declare(strict_types=1);

namespace Cpx;

use Cpx\Commands\AliasesCommand;
use Cpx\Commands\CleanCommand;
use Cpx\Commands\ExecCommand;
use Cpx\Commands\ListCommand;
use Cpx\Commands\RunPackageCommand;
use Cpx\Commands\TinkerCommand;
use Cpx\Commands\UpdateCommand;
use Cpx\Commands\UpgradeCommand;
use Symfony\Component\Console\Application as SymfonyApplication;
use Symfony\Component\Console\Input\InputInterface;

class Application extends SymfonyApplication
{
    public function __construct(?PackageCommandRunner $packageCommandRunner = null)
    {
        parent::__construct('cpx', $this->resolveVersion());
        $this->setAutoExit(false);

        $this->registerCommands($packageCommandRunner ?? new PackageCommandRunner);
    }

    protected function getCommandName(InputInterface $input): ?string
    {
        $command = parent::getCommandName($input);

        return $command === null || $this->has($command)
            ? $command
            : RunPackageCommand::Name;
    }

    private function registerCommands(PackageCommandRunner $packageCommandRunner): void
    {
        $this->addCommands([
            new ListCommand,
            new AliasesCommand,
            new CleanCommand,
            new UpdateCommand,
            new UpgradeCommand,
            new ExecCommand,
            new TinkerCommand,
            new RunPackageCommand($packageCommandRunner),
        ]);
    }

    private function resolveVersion(): string
    {
        $contents = file_get_contents(__DIR__.'/../composer.json');

        if ($contents === false) {
            return 'unknown';
        }

        $decoded = json_decode($contents, true);

        return is_array($decoded) && is_string($decoded['version'] ?? null)
            ? $decoded['version']
            : 'unknown';
    }
}
