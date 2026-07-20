<?php

use Cpx\Cache\PackageMetadata;
use Cpx\Packages\Package;

test('lastRunForDisplay is null when a package has never run', function () {
    $record = new PackageMetadata(Package::parse('laravel/pint'));

    expect($record->lastRunForDisplay())->toBeNull();
});

test('lastRunForDisplay formats the last run timestamp', function () {
    $record = new PackageMetadata(Package::parse('laravel/pint'), lastRunAt: mktime(3, 4, 5, 1, 2, 2024));

    expect($record->lastRunForDisplay())->toBe('2024-01-02 03:04:05');
});
