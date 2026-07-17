<?php

declare(strict_types=1);

namespace Cpx\Commands\Concerns;

use Cpx\Support\Interactivity;
use Cpx\Support\JsonEnvelope;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

trait OutputsJson
{
    private function addJsonOption(): void
    {
        $this->addOption('json', null, InputOption::VALUE_NONE, 'Output the result as JSON');
    }

    private function wantsJson(InputInterface $input): bool
    {
        return $input->getOption('json') === true || ! Interactivity::isInteractive();
    }

    /** @param array<string, mixed> $summary */
    private function outputJsonSuccess(OutputInterface $output, array $summary = []): int
    {
        JsonEnvelope::success($summary)->write($output);

        return Command::SUCCESS;
    }

    /**
     * @param  string|list<string>  $errors
     * @param  array<string, mixed>  $summary
     */
    private function outputJsonFailure(OutputInterface $output, string|array $errors, array $summary = [], int $status = Command::FAILURE): int
    {
        JsonEnvelope::failure($errors, $summary)->write($output);

        return $status;
    }
}
