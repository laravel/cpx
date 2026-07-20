<?php

declare(strict_types=1);

namespace Cpx\Support;

use Laravel\AgentDetector\AgentDetector;
use Symfony\Component\Console\Input\InputInterface;

class Interactivity
{
    private static ?bool $detected = null;

    private static ?bool $fakeEnvironment = null;

    public static function detect(InputInterface $input): void
    {
        self::$detected = ! $input->hasParameterOption(['--no-interaction', '-n', '--json'], true)
            && self::environmentIsInteractive();
    }

    public static function isInteractive(): bool
    {
        return self::$detected ??= self::environmentIsInteractive();
    }

    public static function fake(bool $interactive): void
    {
        self::$fakeEnvironment = $interactive;
        self::$detected = null;
    }

    public static function clearFake(): void
    {
        self::$fakeEnvironment = null;
        self::$detected = null;
    }

    private static function environmentIsInteractive(): bool
    {
        if (self::$fakeEnvironment !== null) {
            return self::$fakeEnvironment;
        }

        return defined('STDIN')
            && stream_isatty(STDIN)
            && ! AgentDetector::detect()->isAgent;
    }
}
