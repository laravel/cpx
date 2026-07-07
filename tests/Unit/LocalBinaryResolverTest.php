<?php

use Cpx\Input\PackageInvocation;
use Cpx\Packages\LocalBinaryResolver;
use Cpx\Packages\Package;
use Cpx\Packages\UserAliases;

test('it resolves a declared bin for an installed unpinned package', function () {
    $this->useIsolatedComposerHome();
    $root = $this->prepareLocalProject();
    $this->installLocalPackage($root, 'laravel/pint', ['builds/pint']);
    $binPath = $this->writeLocalBinary($root, 'pint', "#!/usr/bin/env php\n<?php exit(0);\n");

    $resolved = (new LocalBinaryResolver)->resolve(new PackageInvocation('laravel/pint'));

    expect($resolved)->not->toBeNull()
        ->and($resolved->command)->toBe($binPath);
});

test('it returns null when the package is not installed locally', function () {
    $this->useIsolatedComposerHome();
    $this->prepareLocalProject();

    expect((new LocalBinaryResolver)->resolve(new PackageInvocation('laravel/pint')))->toBeNull();
});

test('it returns null when no local project is discovered', function () {
    $this->useIsolatedComposerHome();
    $this->useWorkingDirectory($this->temporaryDirectory('cpx-no-project'));

    expect((new LocalBinaryResolver)->resolve(new PackageInvocation('laravel/pint')))->toBeNull();
});

test('it returns null when the installed package has no matching local binary', function () {
    $this->useIsolatedComposerHome();
    $root = $this->prepareLocalProject();
    $this->installLocalPackage($root, 'laravel/pint', ['pint']);

    expect((new LocalBinaryResolver)->resolve(new PackageInvocation('laravel/pint')))->toBeNull();
});

test('it resolves an alias only when its backing package is installed locally', function () {
    $this->useIsolatedComposerHome();
    $root = $this->prepareLocalProject();
    $binPath = $this->writeLocalBinary($root, 'pint', "#!/usr/bin/env php\n<?php exit(0);\n");

    UserAliases::open()->put('pint', Package::parse('laravel/pint'))->save();

    expect((new LocalBinaryResolver)->resolve(new PackageInvocation('pint')))->toBeNull();

    $this->installLocalPackage($root, 'laravel/pint', ['pint']);

    expect((new LocalBinaryResolver)->resolve(new PackageInvocation('pint'))?->command)->toBe($binPath);
});

test('it resolves a bare binary name against the project bin-dir', function () {
    $this->useIsolatedComposerHome();
    $root = $this->prepareLocalProject();
    $binPath = $this->writeLocalBinary($root, 'phpunit', "#!/usr/bin/env php\n<?php exit(0);\n");

    expect((new LocalBinaryResolver)->resolve(new PackageInvocation('phpunit'))?->command)->toBe($binPath);
});

test('it selects a declared bin by the first forwarded token for multi-bin packages', function () {
    $this->useIsolatedComposerHome();
    $root = $this->prepareLocalProject();
    $this->installLocalPackage($root, 'vendor/package', ['foo', 'bar']);
    $this->writeLocalBinary($root, 'foo', "#!/usr/bin/env php\n<?php exit(0);\n");
    $barPath = $this->writeLocalBinary($root, 'bar', "#!/usr/bin/env php\n<?php exit(0);\n");

    $resolved = (new LocalBinaryResolver)->resolve(new PackageInvocation('vendor/package', ['bar', '--flag']));

    expect($resolved->command)->toBe($barPath)
        ->and($resolved->invocation->forwardedTokens())->toBe(['--flag']);
});

test('it resolves a version-pinned package only when the installed version satisfies the constraint', function () {
    $this->useIsolatedComposerHome();
    $root = $this->prepareLocalProject();
    $this->installLocalPackage($root, 'laravel/pint', ['pint'], 'v2.1.0');
    $binPath = $this->writeLocalBinary($root, 'pint', "#!/usr/bin/env php\n<?php exit(0);\n");

    $satisfied = (new LocalBinaryResolver)->resolve(new PackageInvocation('laravel/pint:^2.0'));
    $notSatisfied = (new LocalBinaryResolver)->resolve(new PackageInvocation('laravel/pint:^3.0'));

    expect($satisfied)->not->toBeNull()
        ->and($satisfied->command)->toBe($binPath)
        ->and($notSatisfied)->toBeNull();
});

test('it returns null for a version-pinned package when the version cannot be resolved', function () {
    $this->useIsolatedComposerHome();
    $root = $this->prepareLocalProject();
    $this->installLocalPackage($root, 'laravel/pint', ['pint'], 'v2.1.0');
    $this->writeLocalBinary($root, 'pint', "#!/usr/bin/env php\n<?php exit(0);\n");

    // ">=" passes the package grammar but is not a valid Semver constraint.
    expect((new LocalBinaryResolver)->resolve(new PackageInvocation('laravel/pint:>=')))->toBeNull();
});
