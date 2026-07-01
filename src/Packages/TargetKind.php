<?php

declare(strict_types=1);

namespace Cpx\Packages;

enum TargetKind
{
    case Alias;
    case Package;
    case Bare;

    public static function of(string $target): self
    {
        return match (true) {
            array_key_exists($target, PackageAliases::all()) => self::Alias,
            str_contains($target, '/') => self::Package,
            default => self::Bare,
        };
    }
}
