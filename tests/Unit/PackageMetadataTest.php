<?php

use Cpx\Cache\PackageMetadata;
use Cpx\Packages\Package;

test('lastRun is null when a package has never run', function () {
    $record = new PackageMetadata(Package::parse('laravel/pint'));

    expect($record->lastRun())->toBeNull();
});

test('lastRun formats the last run timestamp', function () {
    $record = new PackageMetadata(Package::parse('laravel/pint'), lastRunAt: mktime(3, 4, 5, 1, 2, 2024));

    expect($record->lastRun())->toBe('2024-01-02 03:04:05');
});
