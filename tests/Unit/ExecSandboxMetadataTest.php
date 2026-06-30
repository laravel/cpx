<?php

use Cpx\Cache\ExecSandboxMetadata;
use Cpx\Cache\Metadata;

test('it round-trips a typed exec sandbox record with packages and timestamps', function () {
    $this->useIsolatedComposerHome();

    Metadata::transaction(function (Metadata $metadata) {
        $metadata->execCache['abc123'] = new ExecSandboxMetadata(
            key: 'abc123',
            packages: ['laravel/pint', 'phpstan/phpstan'],
            lastUpdatedAt: 111,
            lastRunAt: 222,
        );
    });

    $sandbox = Metadata::open()->execCache['abc123'];

    expect($sandbox)->toBeInstanceOf(ExecSandboxMetadata::class)
        ->and($sandbox->key)->toBe('abc123')
        ->and($sandbox->packages)->toBe(['laravel/pint', 'phpstan/phpstan'])
        ->and($sandbox->lastUpdatedAt)->toBe(111)
        ->and($sandbox->lastRunAt)->toBe(222);
});
