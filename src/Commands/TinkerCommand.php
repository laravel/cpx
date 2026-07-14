<?php

declare(strict_types=1);

namespace Cpx\Commands;

use Cpx\Packages\Package;
use Cpx\Process\ProcessRunner;
use Cpx\Runtime\Context;
use Cpx\Runtime\ExecEnvironment;
use Cpx\Runtime\ExecVariable;
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
            $runtime = ChildScript::path('child-runtime.php');

            // Include the child runtime so cpx_require() exists inside the project's own REPL.
            return (new ProcessRunner)->run(
                [...$replCommand, $runtime, ...$this->withRuntimeInExecuteCode($tokens, $runtime)],
                [ExecVariable::Bin->value => ExecEnvironment::binPath()],
                cwd: $context->autoloadRoot,
            );
        }

        return $this->launchBundledPsysh($context, $tokens);
    }

    /** @return list<string> */
    private function forwardedTokens(InputInterface $input): array
    {
        return $input instanceof ArgvInput ? $input->getRawTokens(true) : [];
    }

    /**
     * PsySH loads include files only for full REPL runs, so --execute code must require the runtime itself.
     *
     * @param  list<string>  $tokens
     * @return list<string>
     */
    private function withRuntimeInExecuteCode(array $tokens, string $runtime): array
    {
        $prelude = sprintf("require '%s';", addcslashes($runtime, "\\'"));
        $rewritten = [];

        for ($index = 0; $index < count($tokens); $index++) {
            $token = $tokens[$index];

            if ($token === '--execute' && isset($tokens[$index + 1])) {
                $rewritten[] = $token;
                $rewritten[] = "{$prelude} {$tokens[++$index]}";

                continue;
            }

            if (str_starts_with($token, '--execute=')) {
                $rewritten[] = "--execute={$prelude} ".substr($token, strlen('--execute='));

                continue;
            }

            $rewritten[] = $token;
        }

        return $rewritten;
    }

    /**
     * @param  list<string>  $tokens
     */
    private function launchBundledPsysh(Context $context, array $tokens): int
    {
        $environment = (new ExecEnvironment(verbose: $context->verbose))->toEnvironment();
        $environment[ExecVariable::PsyshAutoload->value] = $this->psyshAutoloadPath($context);

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
