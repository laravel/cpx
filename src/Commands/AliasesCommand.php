<?php

declare(strict_types=1);

namespace Cpx\Commands;

use Cpx\Commands\Concerns\OutputsJson;
use Cpx\Packages\Package;
use Cpx\Packages\UserAliases;
use Laravel\Prompts\Elements\Element;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\callout;
use function Laravel\Prompts\info;

#[AsCommand(
    name: 'aliases',
    description: 'Show your package aliases',
)]
class AliasesCommand extends Command
{
    use OutputsJson;

    protected function configure(): void
    {
        $this->addJsonOption();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $userAliases = UserAliases::open()->all();

        ksort($userAliases);

        if ($this->wantsJson($input)) {
            return $this->outputJsonSuccess($output, [
                'aliases' => (object) array_map(fn (Package $package): string => $package->displayString(), $userAliases),
            ]);
        }

        if ($userAliases === []) {
            info('You have no aliases. Create one with `cpx alias`.');

            return self::SUCCESS;
        }

        callout('Your aliases:', [
            Element::keyValueList(array_combine(
                array_map(fn (string $name): string => 'cpx '.$name, array_keys($userAliases)),
                array_map(fn (Package $package): string => $package->displayString(), $userAliases),
            )),
        ]);

        return self::SUCCESS;
    }
}
