<?php

declare(strict_types=1);

namespace Cpx\Commands;

use Cpx\Console;

abstract class Command
{
    public const COLOR_RESET = "\033[0m";

    public const COLOR_GREEN = "\033[1;32m";

    public const COLOR_RED = "\033[1;31m";

    public const COLOR_YELLOW = "\033[1;33m";

    public const COLOR_BLUE = "\033[1;34m";

    public const COLOR_MAGENTA = "\033[1;35m";

    public const COLOR_CYAN = "\033[1;36m";

    public const BACKGROUND_GREEN = "\033[42m";

    public const BACKGROUND_RED = "\033[41m";

    public const BACKGROUND_YELLOW = "\033[43m";

    public const BACKGROUND_BLUE = "\033[44m";

    public const BACKGROUND_MAGENTA = "\033[45m";

    public const BACKGROUND_CYAN = "\033[46m";

    public function __construct(
        protected Console $console,
    ) {}

    protected function line(string $message, string $color = Command::COLOR_RESET): void
    {
        $isBackgroundColor = in_array($color, [Command::BACKGROUND_GREEN, Command::BACKGROUND_RED, Command::BACKGROUND_YELLOW, Command::BACKGROUND_BLUE, Command::BACKGROUND_MAGENTA, Command::BACKGROUND_CYAN]);
        $padding = str_repeat(' ', $isBackgroundColor ? 3 : 0);

        if ($isBackgroundColor) {
            echo $color.str_repeat(' ', mb_strlen($message) + (strlen($padding) * 2)).Command::COLOR_RESET.PHP_EOL;
        }

        echo $color.$padding.$message.$padding.Command::COLOR_RESET.PHP_EOL;

        if ($isBackgroundColor) {
            echo $color.str_repeat(' ', mb_strlen($message) + (strlen($padding) * 2)).Command::COLOR_RESET.PHP_EOL;
            echo PHP_EOL;
        }
    }

    protected function success(string $message): void
    {
        $this->line($message, Command::BACKGROUND_GREEN);
    }

    protected function error(string $message): void
    {
        $this->line($message, Command::BACKGROUND_RED);
    }

    protected function info(string $message): void
    {
        $this->line($message, Command::BACKGROUND_CYAN);
    }
}
