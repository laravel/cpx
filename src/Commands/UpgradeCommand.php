<?php

declare(strict_types=1);

namespace Cpx\Commands;

use Cpx\Runtime\Environment;
use Cpx\SelfUpdate\PharSelfUpdater;
use Cpx\SelfUpdate\SelfUpdater;
use Laravel\Prompts\Prompt;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;

#[AsCommand(
    name: 'upgrade',
    description: 'Upgrade cpx itself',
)]
class UpgradeCommand extends Command
{
    public function __construct(private ?SelfUpdater $updater = null)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        Prompt::setOutput($output);

        if (! Environment::isPhar()) {
            note('Self-update is only available for the phar build. Update cpx through the tool you installed it with.');

            return self::SUCCESS;
        }

        $updater = $this->updater ?? new PharSelfUpdater;

        try {
            if (! $updater->update()) {
                info('cpx is already up-to-date.');

                return self::SUCCESS;
            }

            info("Updated cpx to {$updater->newVersion()}.");

            return self::SUCCESS;
        } catch (Throwable $e) {
            error("Update failed: {$e->getMessage()}");

            return self::FAILURE;
        }
    }
}
