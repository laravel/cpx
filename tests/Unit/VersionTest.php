<?php

declare(strict_types=1);

use Cpx\Version;

test('it returns the injected version when the placeholder was replaced', function () {
    expect(Version::resolve('v2.1.0'))->toBe('v2.1.0');
});

test('it falls back to dev when the placeholder is unreplaced', function () {
    expect(Version::resolve())->toBe('dev')
        ->and(Version::resolve('@git_version@'))->toBe('dev');
});
