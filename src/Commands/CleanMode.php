<?php

declare(strict_types=1);

namespace Cpx\Commands;

enum CleanMode
{
    case All;
    case Sandbox;
    case Period;

    public function cleansPackages(): bool
    {
        return $this !== self::Sandbox;
    }

    public function removesAllPackages(): bool
    {
        return $this === self::All;
    }

    public function removesAllSandboxes(): bool
    {
        return $this !== self::Period;
    }
}
