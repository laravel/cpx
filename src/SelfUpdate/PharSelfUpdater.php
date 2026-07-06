<?php

declare(strict_types=1);

namespace Cpx\SelfUpdate;

use Composer\Semver\VersionParser;
use Cpx\Version;
use Humbug\SelfUpdate\Strategy\GithubStrategy;
use Humbug\SelfUpdate\Updater;

class PharSelfUpdater implements SelfUpdater
{
    private Updater $updater;

    public function __construct()
    {
        $version = Version::resolve();

        $strategy = new GithubStrategy;
        $strategy->setPackageName('cpx/cpx');
        $strategy->setPharName('cpx');
        $strategy->setCurrentLocalVersion($version);
        $strategy->setStability(self::stabilityFor($version));

        $this->updater = new Updater(null, false);
        $this->updater->setStrategyObject($strategy);
    }

    public static function stabilityFor(string $version): string
    {
        return VersionParser::parseStability($version) === 'stable'
            ? GithubStrategy::STABLE
            : GithubStrategy::ANY;
    }

    public function update(): bool
    {
        return $this->updater->update();
    }

    public function newVersion(): string
    {
        return (string) $this->updater->getNewVersion();
    }
}
