<?php

declare(strict_types=1);

namespace Cpx\Runtime;

class ComposerManifest
{
    public static function requires(string $root, string $package): bool
    {
        $manifest = "{$root}/composer.json";

        if (! is_file($manifest)) {
            return false;
        }

        $decoded = json_decode((string) file_get_contents($manifest), true);

        return is_array($decoded) && isset($decoded['require'][$package]);
    }
}
