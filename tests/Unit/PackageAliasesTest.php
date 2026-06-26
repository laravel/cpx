<?php

use Cpx\Packages\PackageAlias;
use Cpx\Packages\PackageAliases;

test('it includes default aliases for common Laravel ecosystem tools', function (string $alias, string $package, string $command) {
    expect(array_key_exists($alias, PackageAliases::all()))->toBeTrue()
        ->and(PackageAliases::all()[$alias]->package)->toBe($package)
        ->and(PackageAliases::all()[$alias]->command)->toBe($command);
})->with([
    ['pint', 'laravel/pint', 'pint'],
    ['phpstan', 'phpstan/phpstan', 'phpstan'],
    ['rector', 'rector/rector', 'rector'],
]);

test('each default alias has the required package runner fields', function () {
    foreach (PackageAliases::all() as $package) {
        expect($package)->toBeInstanceOf(PackageAlias::class)
            ->and($package->name)->not->toBe('')
            ->and($package->description)->not->toBe('')
            ->and($package->command)->not->toBe('')
            ->and($package->package)->toContain('/');
    }
});

test('alias dispatch uses the alias command field when selecting among multiple binaries')->todo(
    'Enable when binary selection uses validated alias metadata.',
);

test('aliases can be added or overridden through user-managed config')->todo(
    'Enable when aliases move from hardcoded defaults to user-managed config.',
);
