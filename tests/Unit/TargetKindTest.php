<?php

use Cpx\Packages\TargetKind;

test('it classifies an aliased short name as an alias', function () {
    expect(TargetKind::of('pint'))->toBe(TargetKind::Alias);
});

test('it classifies a vendor/package string as a package', function () {
    expect(TargetKind::of('laravel/pint'))->toBe(TargetKind::Package)
        ->and(TargetKind::of('laravel/pint:^2.0'))->toBe(TargetKind::Package);
});

test('it classifies an unknown bare name as bare', function () {
    expect(TargetKind::of('phpunit'))->toBe(TargetKind::Bare);
});
