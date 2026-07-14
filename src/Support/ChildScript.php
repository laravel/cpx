<?php

declare(strict_types=1);

namespace Cpx\Support;

use Cpx\Runtime\Environment;

class ChildScript
{
    public static function path(string $script): string
    {
        $pharPath = Environment::pharPath();

        if ($pharPath === '') {
            return dirname(__DIR__, 2)."/files/{$script}";
        }

        // Child processes cannot execute phar:// paths, so hand them a stub that loads the phar first.
        $target = cpx_path($script);
        $stub = sprintf(
            "<?php\n\nPhar::loadPhar('%s', 'cpx.phar');\n\nreturn require 'phar://cpx.phar/files/%s';\n",
            addcslashes($pharPath, "\\'"),
            $script,
        );

        Filesystem::writeAtomic($target, $stub);

        return $target;
    }
}
