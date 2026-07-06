<?php

declare(strict_types=1);

namespace Cpx\Commands;

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
use function Laravel\Prompts\select;

#[AsCommand(
    name: 'unalias',
    description: 'Remove a user-defined alias',
)]
class UnaliasCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('name', InputArgument::OPTIONAL, 'The alias name to remove');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        Prompt::setOutput($output);

        $aliases = UserAliases::open();

        if ($aliases->all() === []) {
            info('You have no aliases to remove.');

            return self::SUCCESS;
        }

        try {
            $name = $this->resolveName($input, $aliases);
        } catch (InvalidArgumentException|NonInteractiveValidationException $e) {
            error($e->getMessage());

            return self::FAILURE;
        }

        $aliases->remove($name)->save();

        info("Alias \"{$name}\" removed.");

        return self::SUCCESS;
    }

    private function resolveName(InputInterface $input, UserAliases $aliases): string
    {
        if ($name = $input->getArgument('name')) {
            if ($error = $this->validateName($name, $aliases)) {
                throw new InvalidArgumentException($error);
            }

            return $name;
        }

        return (string) select(
            label: 'Which alias would you like to remove?',
            options: array_keys($aliases->all()),
            required: 'An alias name must be provided.',
            info: fn (string $name): string => (string) $aliases->find($name),
        );
    }

    private function validateName(string $name, UserAliases $aliases): ?string
    {
        if (! $aliases->has($name)) {
            return "No alias named \"{$name}\" was found.";
        }

        return null;
    }
}
