<?php

declare(strict_types=1);

namespace Cpx\Packages;

use Cpx\Input\PackageInvocation;

class BinResolver
{
    /**
     * @param  array<string, string>  $bins
     */
    public static function resolve(array $bins, PackageInvocation $invocation, string $packageName, ?string $pinnedBin = null): ?ResolvedBin
    {
        if ($pinnedBin !== null) {
            $command = self::match($bins, $pinnedBin);

            return $command === null ? null : new ResolvedBin($command, $invocation);
        }

        if (count($bins) === 1) {
            return new ResolvedBin($bins[array_key_first($bins)], $invocation);
        }

        foreach (array_unique([$invocation->target, $packageName]) as $candidate) {
            $command = self::match($bins, $candidate);

            if ($command !== null) {
                return new ResolvedBin($command, $invocation);
            }
        }

        $forwarded = $invocation->firstForwardedToken();
        $command = $forwarded === null ? null : self::match($bins, $forwarded);

        // Only a forwarded-token match consumes the token; target and package-name matches keep it.
        return $command === null ? null : new ResolvedBin($command, $invocation->withoutFirstForwardedToken());
    }

    /**
     * @param  array<string, string>  $bins
     */
    private static function match(array $bins, string $candidate): ?string
    {
        if (array_key_exists($candidate, $bins)) {
            return $bins[$candidate];
        }

        return in_array($candidate, $bins, true) ? $candidate : null;
    }
}
