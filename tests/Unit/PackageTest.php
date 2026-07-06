<?php

use Cpx\Packages\Package;

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
    'laravel/pint:.',
    'laravel/pint:..',
])->throws(InvalidArgumentException::class);

test('it derives filesystem-safe version segments from hostile constraints', function (string $target) {
    $segment = Package::parse($target)->versionName();

    expect($segment)->not->toBe('')
        ->and(preg_match('#[/\\\\:*?"<>|\s]#', $segment))->toBe(0);
})->with([
    'laravel/pint:^1@dev',
    'laravel/pint:~1.0',
    'laravel/pint:>=2,<3',
    'laravel/pint:dev-main',
    'laravel/pint:*',
    'laravel/pint:1.0.0|2.0.0',
]);

test('it maps an unversioned package to the literal latest segment', function () {
    expect(Package::parse('laravel/pint')->versionName())->toBe('latest');
});

test('it derives a deterministic version segment for the same constraint', function () {
    $first = Package::parse('laravel/pint:^1@dev')->versionName();
    $second = Package::parse('laravel/pint:^1@dev')->versionName();

    expect($first)->toBe($second);
});

test('it disambiguates constraints that slug to the same readable prefix', function () {
    $caret = Package::parse('laravel/pint:^1')->versionName();
    $tilde = Package::parse('laravel/pint:~1')->versionName();

    expect($caret)->not->toBe($tilde);
});

test('it keeps the true package string while using a safe directory key', function () {
    $package = Package::parse('laravel/pint:>=2,<3');

    expect($package->fullPackageString())->toBe('laravel/pint:>=2,<3')
        ->and(preg_match('#[/\\\\:*?"<>|,\s]#', $package->versionName()))->toBe(0);
});
