<?php

declare(strict_types=1);

namespace Cpx\Support;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\error;

class Failure
{
    public static function render(OutputInterface $output, string $message, int $status = Command::FAILURE): int
    {
        if (Interactivity::isInteractive()) {
            error($message);
        } else {
            JsonEnvelope::failure($message)->write($output);
        }

        return $status;
    }
}
