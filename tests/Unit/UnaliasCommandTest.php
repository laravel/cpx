<?php

use Cpx\Application;
use Cpx\Packages\Package;
use Cpx\Packages\UserAliases;
use Symfony\Component\Console\Tester\ApplicationTester;

function unaliasCommandTester(): ApplicationTester
{
    return new ApplicationTester(new Application);
}

test('it removes an existing alias by name', function () {
    $this->useIsolatedComposerHome();

    UserAliases::open()->put('mypint', Package::parse('laravel/pint'))->save();

    $tester = unaliasCommandTester();
    $status = $tester->run(['command' => 'unalias', 'name' => 'mypint']);

    expect($status)->toBe(0)
        ->and($tester->getDisplay())->toContain('Alias "mypint" removed.')
        ->and(UserAliases::open()->has('mypint'))->toBeFalse();
});

test('it fails when the named alias does not exist among other saved aliases', function () {
    $this->useIsolatedComposerHome();

    UserAliases::open()->put('mypint', Package::parse('laravel/pint'))->save();

    $tester = unaliasCommandTester();
    $status = $tester->run(['command' => 'unalias', 'name' => 'missing']);

    expect($status)->toBe(1)
        ->and($tester->getDisplay())->toContain('No alias named "missing" was found.');
});

test('it fails gracefully when the name is omitted outside of an interactive terminal', function () {
    $this->useIsolatedComposerHome();

    UserAliases::open()->put('mypint', Package::parse('laravel/pint'))->save();

    $tester = unaliasCommandTester();
    $status = $tester->run(['command' => 'unalias']);

    expect($status)->toBe(1)
        ->and($tester->getDisplay())->toContain('An alias name must be provided.')
        ->and(UserAliases::open()->has('mypint'))->toBeTrue();
});

test('it reports there is nothing to remove when no aliases exist and the argument is omitted', function () {
    $this->useIsolatedComposerHome();

    $tester = unaliasCommandTester();
    $status = $tester->run(['command' => 'unalias']);

    expect($status)->toBe(0)
        ->and($tester->getDisplay())->toContain('You have no aliases to remove.');
});

test('it fails when the named alias does not exist and no aliases are saved', function () {
    $this->useIsolatedComposerHome();

    $tester = unaliasCommandTester();
    $status = $tester->run(['command' => 'unalias', 'name' => 'mypint']);

    expect($status)->toBe(1)
        ->and($tester->getDisplay())->toContain('No alias named "mypint" was found.');
});
