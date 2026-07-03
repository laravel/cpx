<?php

declare(strict_types=1);

namespace Cpx\SelfUpdate;

use Cpx\Version;
use Humbug\SelfUpdate\Strategy\GithubStrategy;
use Humbug\SelfUpdate\Updater;

class PharSelfUpdater implements SelfUpdater
{
    private Updater $updater;

    public function __construct()
    {
        $strategy = new GithubStrategy;
        $strategy->setPackageName('cpx/cpx');
        $strategy->setPharName('cpx');
        $strategy->setCurrentLocalVersion(Version::resolve());

        $this->updater = new Updater(null, false);
        $this->updater->setStrategyObject($strategy);
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
