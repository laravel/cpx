<?php

use Cpx\Application;
use Cpx\Packages\Package;
use Cpx\Packages\UserAliases;
use Symfony\Component\Console\Tester\CommandTester;

function aliasCommandTester(): CommandTester
{
    $application = new Application;

    return new CommandTester($application->find('alias'));
}

test('it creates an alias non-interactively from positional arguments', function () {
    $this->useIsolatedComposerHome();

    $tester = aliasCommandTester();
    $status = $tester->execute(['package' => 'laravel/pint', 'name' => 'mypint']);

    expect($status)->toBe(0)
        ->and($tester->getDisplay())->toContain('Alias created: cpx mypint')
        ->and(UserAliases::open()->find('mypint')?->fullPackageString())->toBe('laravel/pint');
});

test('it defaults the alias name to the package short name when omitted', function () {
    $this->useIsolatedComposerHome();

    $tester = aliasCommandTester();
    $status = $tester->execute(['package' => 'laravel/pint']);

    expect($status)->toBe(0)
        ->and(UserAliases::open()->find('pint')?->fullPackageString())->toBe('laravel/pint');
});

test('it fails gracefully when the package is omitted outside of an interactive terminal', function () {
    $this->useIsolatedComposerHome();

    $tester = aliasCommandTester();
    $status = $tester->execute([]);

    expect($status)->toBe(1)
        ->and($tester->getDisplay())->toContain('A package name must be provided.')
        ->and(UserAliases::open()->all())->toBe([]);
});

test('it rejects an alias name that collides with a registered command', function () {
    $this->useIsolatedComposerHome();

    $tester = aliasCommandTester();
    $status = $tester->execute(['package' => 'laravel/pint', 'name' => 'clean']);

    expect($status)->toBe(1)
        ->and($tester->getDisplay())->toContain('already a cpx command')
        ->and(UserAliases::open()->has('clean'))->toBeFalse();
});

test('it allows an alias name that collides with a built-in package alias', function () {
    $this->useIsolatedComposerHome();

    $tester = aliasCommandTester();
    $status = $tester->execute(['package' => 'vendor/custom-pint', 'name' => 'pint']);

    expect($status)->toBe(0)
        ->and(UserAliases::open()->find('pint')?->fullPackageString())->toBe('vendor/custom-pint');
});

test('it warns and leaves an existing alias unchanged when the overwrite is not confirmed', function () {
    $this->useIsolatedComposerHome();

    UserAliases::open()->put('tool', Package::parse('vendor/one'))->save();

    $tester = aliasCommandTester();
    $status = $tester->execute(['package' => 'vendor/two', 'name' => 'tool']);

    expect($status)->toBe(0)
        ->and($tester->getDisplay())->toContain('The alias "tool" already runs vendor/one.')
        ->and($tester->getDisplay())->toContain('Alias "tool" was left unchanged.')
        ->and(UserAliases::open()->find('tool')?->fullPackageString())->toBe('vendor/one');
});

test('it overwrites an existing user alias when --force is passed', function () {
    $this->useIsolatedComposerHome();

    UserAliases::open()->put('tool', Package::parse('vendor/one'))->save();

    $tester = aliasCommandTester();
    $status = $tester->execute(['package' => 'vendor/two', 'name' => 'tool', '--force' => true]);

    expect($status)->toBe(0)
        ->and(UserAliases::open()->find('tool')?->fullPackageString())->toBe('vendor/two');
});

test('it rejects an invalid package argument without prompting', function () {
    $this->useIsolatedComposerHome();

    $tester = aliasCommandTester();
    $status = $tester->execute(['package' => 'not-a-package']);

    expect($status)->toBe(1)
        ->and($tester->getDisplay())->toContain('A package name should be in the format');
});

test('it rejects an alias name with characters that would break parsing', function () {
    $this->useIsolatedComposerHome();

    $tester = aliasCommandTester();
    $status = $tester->execute(['package' => 'laravel/pint', 'name' => 'my alias!']);

    expect($status)->toBe(1)
        ->and($tester->getDisplay())->toContain('may only contain letters, numbers');
});
