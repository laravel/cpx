<?php

declare(strict_types=1);

namespace Cpx\Support;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\error;

class Result
{
    /**
     * Interactive success output is command-specific, so this only writes the JSON envelope.
     *
     * @param  array<string, mixed>  $summary
     */
    public static function success(OutputInterface $output, array $summary = []): int
    {
        JsonEnvelope::success($summary)->write($output);

        return Command::SUCCESS;
    }

    /**
     * @param  string|list<string>  $errors
     * @param  array<string, mixed>  $summary
     */
    public static function failure(OutputInterface $output, string|array $errors, array $summary = [], int $status = Command::FAILURE): int
    {
        if (! Interactivity::isInteractive()) {
            JsonEnvelope::failure($errors, $summary)->write($output);

            return $status;
        }

        foreach ((array) $errors as $message) {
            error($message);
        }

        return $status;
    }
}
