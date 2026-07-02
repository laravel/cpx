<?php

declare(strict_types=1);

namespace Cpx\Commands;

use Cpx\Packages\Package;
use Cpx\Packages\UserAliases;
use InvalidArgumentException;
use Laravel\Prompts\Exceptions\NonInteractiveValidationException;
use Laravel\Prompts\Prompt;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\text;

#[AsCommand(
    name: 'alias',
    description: 'Create a shortcut command for a Composer package',
)]
class AliasCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('package', InputArgument::OPTIONAL, 'The package to alias, e.g. <vendor>/<package>[:version]');
        $this->addArgument('name', InputArgument::OPTIONAL, 'The alias name to run the package as, e.g. "cpx <name>"');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        Prompt::setOutput($output);

        try {
            $package = $this->resolvePackage($input);
            $name = $this->resolveName($input, $package);
        } catch (InvalidArgumentException|NonInteractiveValidationException $e) {
            error($e->getMessage());

            return self::FAILURE;
        }

        UserAliases::open()->put($name, $package)->save();

        info("Alias created: cpx {$name} now runs {$package}.");

        return self::SUCCESS;
    }

    private function resolvePackage(InputInterface $input): Package
    {
        if ($package = $input->getArgument('package')) {
            if ($error = $this->validatePackage($package)) {
                throw new InvalidArgumentException($error);
            }

            return Package::parse($package);
        }

        return Package::parse(text(
            label: 'Which package would you like to alias?',
            placeholder: '<vendor>/<package>[:version]',
            required: 'A package name must be provided.',
            validate: $this->validatePackage(...),
        ));
    }

    private function validatePackage(string $value): ?string
    {
        try {
            Package::parse($value);

            return null;
        } catch (InvalidArgumentException $e) {
            return $e->getMessage();
        }
    }

    private function resolveName(InputInterface $input, Package $package): string
    {
        if ($name = $input->getArgument('name')) {
            if ($error = $this->validateName($name)) {
                throw new InvalidArgumentException($error);
            }

            return $name;
        }

        return text(
            label: 'What should the alias be called?',
            default: $package->name,
            validate: $this->validateName(...),
        );
    }

    private function validateName(string $name): ?string
    {
        if (preg_match('/^[a-zA-Z0-9][a-zA-Z0-9_.-]*$/', $name) !== 1) {
            return 'An alias name may only contain letters, numbers, dots, dashes and underscores.';
        }

        if ($this->getApplication()?->has($name) === true) {
            return "\"{$name}\" is already a cpx command and cannot be used as an alias name.";
        }

        return null;
    }
}
