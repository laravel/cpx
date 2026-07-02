<?php

declare(strict_types=1);

namespace Cpx\Composer;

use Composer\InstalledVersions;
use RuntimeException;

enum ComposerSource
{
    case Bundled;
    case Device;

    /** @return list<string> */
    public function binary(): array
    {
        return match ($this) {
            self::Bundled => [PHP_BINARY, self::bundledComposerPath()],
            self::Device => ['composer'],
        };
    }

    private static function bundledComposerPath(): string
    {
        $path = InstalledVersions::getInstallPath('composer/composer');

        if ($path === null) {
            throw new RuntimeException('Unable to locate the bundled Composer binary.');
        }

        return "{$path}/bin/composer";
    }
}
