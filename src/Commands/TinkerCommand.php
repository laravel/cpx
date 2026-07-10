<?php

declare(strict_types=1);

namespace Cpx\Commands;

use Cpx\Packages\Package;
use Cpx\Process\ProcessRunner;
use Cpx\Runtime\Context;
use Cpx\Runtime\ExecEnvironment;
use Cpx\Runtime\LoaderRegistry;
use Cpx\Runtime\PhpExecutionHelper;
use Cpx\Runtime\ReplLauncher;
use Cpx\Support\ChildScript;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\error;

#[AsCommand(
    name: 'tinker',
    description: 'Open an interactive REPL',
)]
class TinkerCommand extends Command
{
    protected function configure(): void
    {
        // Let undeclared REPL args/options pass through instead of failing validation;
        $this->ignoreValidationErrors();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $workingDirectory = getcwd();

        if ($workingDirectory === false) {
            error('Unable to determine the current working directory.');

            return self::FAILURE;
        }

        $context = new Context(
            workingDirectory: $workingDirectory,
            autoloadRoot: PhpExecutionHelper::findAutoloadRoot($workingDirectory),
            verbose: $output->isVerbose(),
        );

        $tokens = $this->forwardedTokens($input);
        $loader = LoaderRegistry::resolve($context);
        $replCommand = $loader instanceof ReplLauncher ? $loader->replCommand($context) : null;

        if ($replCommand !== null) {
            return (new ProcessRunner)->run([...$replCommand, ...$tokens]);
        }

        return $this->launchBundledPsysh($context, $tokens);
    }

    /** @return list<string> */
    private function forwardedTokens(InputInterface $input): array
    {
        $tokens = $input instanceof ArgvInput ? $input->getRawTokens() : [];

        if (($tokens[0] ?? null) === 'tinker') {
            array_shift($tokens);
        }

        return $tokens;
    }

    /**
     * @param  list<string>  $tokens
     */
    private function launchBundledPsysh(Context $context, array $tokens): int
    {
        $environment = (new ExecEnvironment(verbose: $context->verbose))->toEnvironment();
        $environment['CPX_TINKER_PSYSH_AUTOLOAD'] = $this->psyshAutoloadPath($context);

        return (new ProcessRunner)->run(
            [PHP_BINARY, ChildScript::path('tinker-runner.php'), ...$tokens],
            $environment,
        );
    }

    private function psyshAutoloadPath(Context $context): string|false
    {
        if ($context->autoloadRoot !== null && is_dir("{$context->autoloadRoot}/vendor/psy/psysh")) {
            return false;
        }

        $installDir = Package::parse('psy/psysh')->installOrUpdatePackage();

        return "{$installDir}/vendor/autoload.php";
    }
}
