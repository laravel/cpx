<?php

declare(strict_types=1);

namespace Cpx\Commands\Concerns;

use Cpx\Support\Interactivity;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

trait OutputsJson
{
    private function addJsonOption(): void
    {
        $this->addOption(Interactivity::JSON_OPTION, null, InputOption::VALUE_NONE, 'Output the result as JSON');
    }

    private function wantsJson(InputInterface $input): bool
    {
        return $input->getOption(Interactivity::JSON_OPTION) === true || ! Interactivity::isInteractive();
    }
}
