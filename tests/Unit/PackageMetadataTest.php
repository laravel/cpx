<?php

use Cpx\Cache\PackageMetadata;
use Cpx\Packages\Package;
use Cpx\Packages\PackageAliases;

test('a package alias is resolved from the registry, never stored on the record', function () {
    expect(property_exists(PackageMetadata::class, 'alias'))->toBeFalse();

    $aliasForPint = null;

    foreach (PackageAliases::all() as $alias => $definition) {
        if ($definition->package === 'laravel/pint') {
            $aliasForPint = $alias;

            break;
        }
    }

    expect($aliasForPint)->toBe('pint');
});

test('lastRunForDisplay falls back to N/A when a package has never run', function () {
    $record = new PackageMetadata(Package::parse('laravel/pint'));

    expect($record->lastRunForDisplay())->toBe('N/A');
});
