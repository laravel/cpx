<?php

use Cpx\Cache\PackageMetadata;
use Cpx\Packages\Package;

test('lastRunForDisplay falls back to N/A when a package has never run', function () {
    $record = new PackageMetadata(Package::parse('laravel/pint'));

    expect($record->lastRunForDisplay())->toBe('N/A');
});
