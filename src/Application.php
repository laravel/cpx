<?php

declare(strict_types=1);

namespace Cpx;

use Cpx\Commands\AliasCommand;
use Cpx\Commands\AliasesCommand;
use Cpx\Commands\CleanCommand;
use Cpx\Commands\ExecCommand;
use Cpx\Commands\InstalledCommand;
use Cpx\Commands\RunPackageCommand;
use Cpx\Commands\SandboxCommand;
use Cpx\Commands\TinkerCommand;
use Cpx\Commands\UnaliasCommand;
use Cpx\Commands\UpdateCommand;
use Cpx\Composer\ComposerRunner;
use Cpx\Packages\PackageCommandRunner;
use Cpx\Support\Interactivity;
use Cpx\Support\PromptFallbacks;
use Cpx\Support\Result;
use Laravel\Prompts\Prompt;
use Symfony\Component\Console\Application as SymfonyApplication;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

class Application extends SymfonyApplication
{
    public function __construct(?PackageCommandRunner $packageCommandRunner = null)
    {
        parent::__construct('cpx', Version::resolve());
        $this->setAutoExit(false);

        $this->registerCommands($packageCommandRunner ?? new PackageCommandRunner);
    }

    public function run(?InputInterface $input = null, ?OutputInterface $output = null): int
    {
        $input ??= new ArgvInput;
        $output ??= new ConsoleOutput;

        Prompt::setOutput($output);
        PromptFallbacks::register($input, $output);

        if ($input instanceof ArgvInput) {
            $tokens = $input->getRawTokens();

            // Bundled Composer re-invokes the phar with this token
            if (($tokens[0] ?? null) === ComposerRunner::REINVOKE_TOKEN) {
                return ComposerRunner::runInProcess(array_slice($tokens, 1), $output);
            }

            if ($this->shouldRunPackageFallback($input)) {
                $input = new ArgvInput(['cpx', RunPackageCommand::NAME, '--', ...$tokens]);
            }
        }

        Interactivity::detect($input);

        if (Interactivity::isInteractive()) {
            return parent::run($input, $output);
        }

        $input->setInteractive(false);
        Prompt::interactive(false);
        $this->setCatchExceptions(false);

        try {
            return parent::run($input, $output);
        } catch (Throwable $exception) {
            Result::failure($output, $exception->getMessage());

            if ($output instanceof ConsoleOutputInterface) {
                $this->renderThrowable($exception, $output->getErrorOutput());
            }

            return Command::FAILURE;
        }
    }

    protected function getCommandName(InputInterface $input): ?string
    {
        $command = parent::getCommandName($input);

        return $command === null || $this->has($command)
            ? $command
            : RunPackageCommand::NAME;
    }

    private function registerCommands(PackageCommandRunner $packageCommandRunner): void
    {
        $this->addCommands([
            new InstalledCommand,
            new AliasCommand,
            new AliasesCommand,
            new UnaliasCommand,
            new CleanCommand,
            new UpdateCommand,
            new ExecCommand,
            new TinkerCommand,
            new SandboxCommand,
            new RunPackageCommand($packageCommandRunner),
        ]);
    }

    private function shouldRunPackageFallback(ArgvInput $input): bool
    {
        $command = $input->getRawTokens()[0] ?? null;

        return is_string($command)
            && ! str_starts_with($command, '-')
            && ! $this->has($command);
    }
}
