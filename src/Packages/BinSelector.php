<?php

declare(strict_types=1);

namespace Cpx\Packages;

use Cpx\Input\PackageInvocation;

class BinSelector
{
    /**
     * @param  array<string, string>  $bins
     */
    public function select(array $bins, PackageInvocation $invocation, string $packageName, ?string $pinnedBin = null): ?ResolvedBin
    {
        if ($pinnedBin !== null) {
            $command = $this->match($bins, $pinnedBin);

            return $command === null ? null : new ResolvedBin($command, $invocation);
        }

        if (count($bins) === 1) {
            return new ResolvedBin($bins[array_key_first($bins)], $invocation);
        }

        $candidates = array_filter([
            $invocation->target,
            $invocation->firstForwardedToken(),
            $packageName,
        ]);

        foreach (array_unique($candidates) as $candidate) {
            $command = $this->match($bins, $candidate);

            if ($command === null) {
                continue;
            }

            return $invocation->firstForwardedToken() === $candidate
                ? new ResolvedBin($command, $invocation->withoutFirstForwardedToken())
                : new ResolvedBin($command, $invocation);
        }

        return null;
    }

    /**
     * @param  array<string, string>  $bins
     */
    private function match(array $bins, string $candidate): ?string
    {
        if (array_key_exists($candidate, $bins)) {
            return $bins[$candidate];
        }

        return in_array($candidate, $bins, true) ? $candidate : null;
    }
}
