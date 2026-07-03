<?php

declare(strict_types=1);

namespace Cpx\Composer;

use Composer\InstalledVersions;
use Cpx\Runtime\Environment;
use RuntimeException;

enum ComposerSource
{
    case Bundled;
    case Device;

    /** @return list<string> */
    public function binary(): array
    {
        return match ($this) {
            self::Bundled => self::bundledBinary(),
            self::Device => ['composer'],
        };
    }

    /** @return list<string> */
    private static function bundledBinary(): array
    {
        $pharPath = Environment::pharPath();

        return $pharPath === ''
            ? [PHP_BINARY, self::bundledComposerPath()]
            : [PHP_BINARY, $pharPath, ComposerRunner::REINVOKE_TOKEN];
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
