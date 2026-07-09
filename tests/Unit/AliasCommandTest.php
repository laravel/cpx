<?php

use Cpx\Application;
use Cpx\Packages\Package;
use Cpx\Packages\UserAliases;
use Symfony\Component\Console\Tester\ApplicationTester;

function aliasCommandTester(): ApplicationTester
{
    return new ApplicationTester(new Application);
}

test('it creates an alias non-interactively from positional arguments', function () {
    $this->useIsolatedComposerHome();
    prepareCachedPackage('laravel/pint', ['pint']);

    $tester = aliasCommandTester();
    $status = $tester->run(['command' => 'alias', 'package' => 'laravel/pint', 'name' => 'mypint']);

    expect($status)->toBe(0)
        ->and($tester->getDisplay())->toContain('Alias created')
        ->and($tester->getDisplay())->toContain('`cpx mypint` now runs laravel/pint')
        ->and(UserAliases::open()->find('mypint')?->fullPackageString())->toBe('laravel/pint');
});

test('it defaults the alias name to the package short name when omitted', function () {
    $this->useIsolatedComposerHome();
    prepareCachedPackage('laravel/pint', ['pint']);

    $tester = aliasCommandTester();
    $status = $tester->run(['command' => 'alias', 'package' => 'laravel/pint']);

    expect($status)->toBe(0)
        ->and(UserAliases::open()->find('pint')?->fullPackageString())->toBe('laravel/pint');
});

test('it fails gracefully when the package is omitted outside of an interactive terminal', function () {
    $this->useIsolatedComposerHome();

    $tester = aliasCommandTester();
    $status = $tester->run(['command' => 'alias']);

    expect($status)->toBe(1)
        ->and($tester->getDisplay())->toContain('A package name must be provided.')
        ->and(UserAliases::open()->all())->toBe([]);
});

test('it rejects an alias name that collides with a registered command', function () {
    $this->useIsolatedComposerHome();

    $tester = aliasCommandTester();
    $status = $tester->run(['command' => 'alias', 'package' => 'laravel/pint', 'name' => 'clean']);

    expect($status)->toBe(1)
        ->and($tester->getDisplay())->toContain('already a cpx command')
        ->and(UserAliases::open()->has('clean'))->toBeFalse();
});

test('it warns before overwriting an existing alias of the same name', function () {
    $this->useIsolatedComposerHome();
    prepareCachedPackage('vendor/two', ['two']);

    UserAliases::open()->put('tool', Package::parse('vendor/one'))->save();

    $tester = aliasCommandTester();
    $status = $tester->run(['command' => 'alias', 'package' => 'vendor/two', 'name' => 'tool']);

    expect($status)->toBe(0)
        ->and($tester->getDisplay())->toContain('The alias "tool" is currently mapped to vendor/one.')
        ->and(UserAliases::open()->find('tool')?->fullPackageString())->toBe('vendor/two');
});

test('it overwrites an existing user alias when --force is passed', function () {
    $this->useIsolatedComposerHome();
    prepareCachedPackage('vendor/two', ['two']);

    UserAliases::open()->put('tool', Package::parse('vendor/one'))->save();

    $tester = aliasCommandTester();
    $status = $tester->run(['command' => 'alias', 'package' => 'vendor/two', 'name' => 'tool', '--force' => true]);

    expect($status)->toBe(0)
        ->and(UserAliases::open()->find('tool')?->fullPackageString())->toBe('vendor/two');
});

test('it rejects an invalid package argument without prompting', function () {
    $this->useIsolatedComposerHome();

    $tester = aliasCommandTester();
    $status = $tester->run(['command' => 'alias', 'package' => 'not-a-package']);

    expect($status)->toBe(1)
        ->and($tester->getDisplay())->toContain('A package name should be in the format');
});

test('it rejects an alias name with characters that would break parsing', function () {
    $this->useIsolatedComposerHome();

    $tester = aliasCommandTester();
    $status = $tester->run(['command' => 'alias', 'package' => 'laravel/pint', 'name' => 'my alias!']);

    expect($status)->toBe(1)
        ->and($tester->getDisplay())->toContain('may only contain letters, numbers');
});

test('it pins the alias to the binary chosen with --bin for a multi-binary package', function () {
    $this->useIsolatedComposerHome();
    prepareCachedPackage('vendor/package', ['foo', 'bar']);

    $tester = aliasCommandTester();
    $status = $tester->run(['command' => 'alias', 'package' => 'vendor/package', 'name' => 'tool', '--bin' => 'bar']);

    expect($status)->toBe(0)
        ->and($tester->getDisplay())->toContain('Alias created')
        ->and($tester->getDisplay())->toContain('`cpx tool` now runs vendor/package (bar)');

    $alias = UserAliases::open()->find('tool');

    expect($alias?->fullPackageString())->toBe('vendor/package')
        ->and($alias?->bin)->toBe('bar');
});

test('it persists the chosen binary to the aliases file', function () {
    $this->useIsolatedComposerHome();
    prepareCachedPackage('vendor/package', ['foo', 'bar']);

    aliasCommandTester()->run(['command' => 'alias', 'package' => 'vendor/package', 'name' => 'tool', '--bin' => 'foo']);

    $stored = json_decode((string) file_get_contents(cpx_path('aliases.json')), true);

    expect($stored)->toBe(['tool' => ['package' => 'vendor/package', 'bin' => 'foo']]);
});

test('it rejects a --bin value the package does not provide', function () {
    $this->useIsolatedComposerHome();
    prepareCachedPackage('vendor/package', ['foo', 'bar']);

    $tester = aliasCommandTester();
    $status = $tester->run(['command' => 'alias', 'package' => 'vendor/package', 'name' => 'tool', '--bin' => 'baz']);

    expect($status)->toBe(1)
        ->and($tester->getDisplay())->toContain('"baz" is not a binary provided by vendor/package')
        ->and($tester->getDisplay())->toContain('Available binaries: foo, bar.')
        ->and(UserAliases::open()->has('tool'))->toBeFalse();
});

test('it fails for a multi-binary package when no binary is chosen non-interactively', function () {
    $this->useIsolatedComposerHome();
    prepareCachedPackage('vendor/package', ['foo', 'bar']);

    $tester = aliasCommandTester();
    $status = $tester->setInputs([])->run(
        ['command' => 'alias', 'package' => 'vendor/package', 'name' => 'tool'],
        ['interactive' => false],
    );

    expect($status)->toBe(1)
        ->and($tester->getDisplay())->toContain('exposes multiple binaries (foo, bar)')
        ->and(UserAliases::open()->has('tool'))->toBeFalse();
});

test('it rejects a package that does not provide any binaries', function () {
    $this->useIsolatedComposerHome();
    prepareCachedPackage('vendor/package', []);

    $tester = aliasCommandTester();
    $status = $tester->run(['command' => 'alias', 'package' => 'vendor/package', 'name' => 'tool']);

    expect($status)->toBe(1)
        ->and($tester->getDisplay())->toContain('vendor/package does not provide any binaries.')
        ->and(UserAliases::open()->has('tool'))->toBeFalse();
});

test('it does not require a binary choice for a single-binary package', function () {
    $this->useIsolatedComposerHome();
    prepareCachedPackage('vendor/package', ['only']);

    $tester = aliasCommandTester();
    $status = $tester->run(['command' => 'alias', 'package' => 'vendor/package', 'name' => 'tool']);

    expect($status)->toBe(0)
        ->and(UserAliases::open()->find('tool')?->bin)->toBeNull();
});
