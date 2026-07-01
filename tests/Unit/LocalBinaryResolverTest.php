<?php

use Cpx\Input\PackageInvocation;
use Cpx\Packages\LocalBinaryResolver;

test('it resolves a declared bin for an installed unpinned package', function () {
    $root = $this->prepareLocalProject();
    $this->installLocalPackage($root, 'laravel/pint', ['builds/pint']);
    $binPath = $this->writeLocalBinary($root, 'pint', "#!/usr/bin/env php\n<?php exit(0);\n");

    $resolved = (new LocalBinaryResolver)->resolve(new PackageInvocation('laravel/pint'));

    expect($resolved)->not->toBeNull()
        ->and($resolved->command)->toBe($binPath);
});

test('it resolves a version-pinned package only when the installed version satisfies the constraint', function () {
    $root = $this->prepareLocalProject();
    $this->installLocalPackage($root, 'laravel/pint', ['pint'], 'v2.1.0');
    $binPath = $this->writeLocalBinary($root, 'pint', "#!/usr/bin/env php\n<?php exit(0);\n");

    $satisfied = (new LocalBinaryResolver)->resolve(new PackageInvocation('laravel/pint:^2.0'));
    $notSatisfied = (new LocalBinaryResolver)->resolve(new PackageInvocation('laravel/pint:^3.0'));

    expect($satisfied)->not->toBeNull()
        ->and($satisfied->command)->toBe($binPath)
        ->and($notSatisfied)->toBeNull();
});

test('it returns null when the package is not installed locally', function () {
    $this->prepareLocalProject();

    expect((new LocalBinaryResolver)->resolve(new PackageInvocation('laravel/pint')))->toBeNull();
});

test('it selects a declared bin by the first forwarded token for multi-bin packages', function () {
    $root = $this->prepareLocalProject();
    $this->installLocalPackage($root, 'vendor/package', ['foo', 'bar']);
    $this->writeLocalBinary($root, 'foo', "#!/usr/bin/env php\n<?php exit(0);\n");
    $barPath = $this->writeLocalBinary($root, 'bar', "#!/usr/bin/env php\n<?php exit(0);\n");

    $resolved = (new LocalBinaryResolver)->resolve(new PackageInvocation('vendor/package', ['bar', '--flag']));

    expect($resolved->command)->toBe($barPath)
        ->and($resolved->invocation->forwardedTokens())->toBe(['--flag']);
});
