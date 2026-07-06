<?php

declare(strict_types=1);

use Cpx\SelfUpdate\PharSelfUpdater;
use Humbug\SelfUpdate\Strategy\GithubStrategy;

test('it only offers stable releases to stable builds', function () {
    expect(PharSelfUpdater::stabilityFor('v2.1.0'))->toBe(GithubStrategy::STABLE);
});

test('it offers any release stability to pre-release builds', function () {
    expect(PharSelfUpdater::stabilityFor('v2.1.0-beta1'))->toBe(GithubStrategy::ANY)
        ->and(PharSelfUpdater::stabilityFor('v2.1.0-RC1'))->toBe(GithubStrategy::ANY);
});

test('it offers any release stability to dev builds', function () {
    expect(PharSelfUpdater::stabilityFor('dev'))->toBe(GithubStrategy::ANY);
});
