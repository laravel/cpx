<?php

use Cpx\Input\PackageInvocation;
use Cpx\Packages\LocalBinaryResolver;
use Cpx\Packages\Package;

test('it resolves a declared bin for an installed unpinned package', function () {
    $this->useIsolatedComposerHome();
    $root = $this->prepareLocalProject();
    $this->installLocalPackage($root, 'laravel/pint', ['builds/pint']);
    $binPath = $this->writeLocalBinary($root, 'pint', noopBinary());

    $resolved = (new LocalBinaryResolver)->resolve(Package::parse('laravel/pint'), new PackageInvocation('laravel/pint'));

    expect($resolved)->not->toBeNull()
        ->and($resolved->command)->toBe($binPath);
});

test('it returns null when the package is not installed locally', function () {
    $this->useIsolatedComposerHome();
    $this->prepareLocalProject();

    expect((new LocalBinaryResolver)->resolve(Package::parse('laravel/pint'), new PackageInvocation('laravel/pint')))->toBeNull();
});

test('it returns null when no local project is discovered', function () {
    $this->useIsolatedComposerHome();
    $this->useWorkingDirectory($this->temporaryDirectory('cpx-no-project'));

    expect((new LocalBinaryResolver)->resolve(Package::parse('laravel/pint'), new PackageInvocation('laravel/pint')))->toBeNull()
        ->and((new LocalBinaryResolver)->resolveBare(new PackageInvocation('phpunit')))->toBeNull();
});

test('it returns null when the installed package has no matching local binary', function () {
    $this->useIsolatedComposerHome();
    $root = $this->prepareLocalProject();
    $this->installLocalPackage($root, 'laravel/pint', ['pint']);

    expect((new LocalBinaryResolver)->resolve(Package::parse('laravel/pint'), new PackageInvocation('laravel/pint')))->toBeNull();
});

test('it resolves an aliased package against the alias invocation target', function () {
    $this->useIsolatedComposerHome();
    $root = $this->prepareLocalProject();
    $binPath = $this->writeLocalBinary($root, 'pint', noopBinary());
    $package = Package::parse('laravel/pint');

    expect((new LocalBinaryResolver)->resolve($package, new PackageInvocation('pint')))->toBeNull();

    $this->installLocalPackage($root, 'laravel/pint', ['pint']);

    expect((new LocalBinaryResolver)->resolve($package, new PackageInvocation('pint'))?->command)->toBe($binPath);
});

test('it resolves a bare binary name against the project bin-dir', function () {
    $this->useIsolatedComposerHome();
    $root = $this->prepareLocalProject();
    $binPath = $this->writeLocalBinary($root, 'phpunit', noopBinary());

    expect((new LocalBinaryResolver)->resolveBare(new PackageInvocation('phpunit'))?->command)->toBe($binPath);
});

test('it selects a declared bin by the first forwarded token for multi-bin packages', function () {
    $this->useIsolatedComposerHome();
    $root = $this->prepareLocalProject();
    $this->installLocalPackage($root, 'vendor/package', ['foo', 'bar']);
    $this->writeLocalBinary($root, 'foo', noopBinary());
    $barPath = $this->writeLocalBinary($root, 'bar', noopBinary());

    $resolved = (new LocalBinaryResolver)->resolve(Package::parse('vendor/package'), new PackageInvocation('vendor/package', ['bar', '--flag']));

    expect($resolved->command)->toBe($barPath)
        ->and($resolved->invocation->forwardedTokens())->toBe(['--flag']);
});

test('it resolves a pinned bin without consuming forwarded tokens', function () {
    $this->useIsolatedComposerHome();
    $root = $this->prepareLocalProject();
    $this->installLocalPackage($root, 'laravel/pint', ['pint', 'extra']);
    $binPath = $this->writeLocalBinary($root, 'pint', noopBinary());
    $this->writeLocalBinary($root, 'extra', noopBinary());

    $resolved = (new LocalBinaryResolver)->resolve(Package::parse('laravel/pint')->withBin('pint'), new PackageInvocation('mytool', ['--flag']));

    expect($resolved->command)->toBe($binPath)
        ->and($resolved->invocation->forwardedTokens())->toBe(['--flag']);
});

test('it resolves a bin pinned by its declared path to the bin-dir proxy', function () {
    $this->useIsolatedComposerHome();
    $root = $this->prepareLocalProject();
    $this->installLocalPackage($root, 'laravel/pint', ['builds/pint']);
    $binPath = $this->writeLocalBinary($root, 'pint', noopBinary());

    $resolved = (new LocalBinaryResolver)->resolve(Package::parse('laravel/pint')->withBin('builds/pint'), new PackageInvocation('mytool'));

    expect($resolved?->command)->toBe($binPath);
});

test('it returns null when the pinned bin is not declared by the package', function () {
    $this->useIsolatedComposerHome();
    $root = $this->prepareLocalProject();
    $this->installLocalPackage($root, 'laravel/pint', ['pint']);
    $this->writeLocalBinary($root, 'pint', noopBinary());

    expect((new LocalBinaryResolver)->resolve(Package::parse('laravel/pint')->withBin('nope'), new PackageInvocation('mytool')))->toBeNull();
});

test('it resolves a version-pinned package only when the installed version satisfies the constraint', function () {
    $this->useIsolatedComposerHome();
    $root = $this->prepareLocalProject();
    $this->installLocalPackage($root, 'laravel/pint', ['pint'], 'v2.1.0');
    $binPath = $this->writeLocalBinary($root, 'pint', noopBinary());

    $satisfied = (new LocalBinaryResolver)->resolve(Package::parse('laravel/pint:^2.0'), new PackageInvocation('laravel/pint:^2.0'));
    $notSatisfied = (new LocalBinaryResolver)->resolve(Package::parse('laravel/pint:^3.0'), new PackageInvocation('laravel/pint:^3.0'));

    expect($satisfied)->not->toBeNull()
        ->and($satisfied->command)->toBe($binPath)
        ->and($notSatisfied)->toBeNull();
});

test('it returns null for a version-pinned package when the version cannot be resolved', function () {
    $this->useIsolatedComposerHome();
    $root = $this->prepareLocalProject();
    $this->installLocalPackage($root, 'laravel/pint', ['pint'], 'v2.1.0');
    $this->writeLocalBinary($root, 'pint', noopBinary());

    // ">=" passes the package grammar but is not a valid Semver constraint.
    expect((new LocalBinaryResolver)->resolve(Package::parse('laravel/pint:>='), new PackageInvocation('laravel/pint:>=')))->toBeNull();
});
