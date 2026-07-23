<?php

declare(strict_types=1);

namespace Cpx\Support;

class Str
{
    public static function stripAnsi(string $value): string
    {
        return preg_replace('/\x1B(?:[@-Z\\-_]|\[[0-?]*[ -\/]*[@-~])/', '', $value) ?? $value;
    }
}
