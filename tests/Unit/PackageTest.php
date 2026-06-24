<?php

use Cpx\Package;

test('it parses a package target without a version constraint', function () {
    $package = Package::parse('laravel/pint');

    expect($package->vendor)->toBe('laravel')
        ->and($package->name)->toBe('pint')
        ->and($package->version)->toBeNull()
        ->and($package->versionName())->toBe('latest')
        ->and($package->fullPackageString())->toBe('laravel/pint');
});

test('it preserves version constraints and stability flags', function (string $target, string $version) {
    $package = Package::parse($target);

    expect($package->version)->toBe($version)
        ->and($package->fullPackageString())->toBe($target);
})->with([
    ['laravel/pint:^1.2', '^1.2'],
    ['laravel/pint:1.0.0', '1.0.0'],
    ['laravel/pint:dev-main', 'dev-main'],
    ['laravel/pint:^1@dev', '^1@dev'],
]);

test('it accepts composer package names with supported punctuation', function (string $target) {
    $package = Package::parse($target);

    expect($package->fullPackageString())->toBe($target);
})->with([
    'vendor-name/package_name',
    'vendor.name/package-name',
    'vendor123/package.456',
]);

test('it rejects invalid package targets', function (string $target) {
    Package::parse($target);
})->with([
    '',
    'laravel',
    '/pint',
    'laravel/',
    'Laravel/pint',
    'laravel/Pint',
    'laravel/pint extra',
    '../laravel/pint',
    'laravel/../pint',
    'laravel/pint;rm -rf',
    'laravel/pint|cat',
    'laravel/pint/name',
    'laravel/pint:',
])->throws(InvalidArgumentException::class);

test('package cache keys are derived from validated identifiers or stable safe hashes')->todo(
    'Enable when cache keys are redesigned to avoid raw constraint punctuation.',
);
