<?php

use Cpx\Application;
use Cpx\Packages\Package;
use Cpx\Packages\UserAliases;
use Symfony\Component\Console\Tester\CommandTester;

function forgetCommandTester(): CommandTester
{
    $application = new Application;

    return new CommandTester($application->find('forget'));
}

test('it removes an existing alias by name', function () {
    $this->useIsolatedComposerHome();

    UserAliases::open()->put('mypint', Package::parse('laravel/pint'))->save();

    $tester = forgetCommandTester();
    $status = $tester->execute(['name' => 'mypint']);

    expect($status)->toBe(0)
        ->and($tester->getDisplay())->toContain('Alias "mypint" removed.')
        ->and(UserAliases::open()->has('mypint'))->toBeFalse();
});

test('it fails when the named alias does not exist among other saved aliases', function () {
    $this->useIsolatedComposerHome();

    UserAliases::open()->put('mypint', Package::parse('laravel/pint'))->save();

    $tester = forgetCommandTester();
    $status = $tester->execute(['name' => 'missing']);

    expect($status)->toBe(1)
        ->and($tester->getDisplay())->toContain('No alias named "missing" was found.');
});

test('it fails gracefully when the name is omitted outside of an interactive terminal', function () {
    $this->useIsolatedComposerHome();

    UserAliases::open()->put('mypint', Package::parse('laravel/pint'))->save();

    $tester = forgetCommandTester();
    $status = $tester->execute([]);

    expect($status)->toBe(1)
        ->and($tester->getDisplay())->toContain('An alias name must be provided.')
        ->and(UserAliases::open()->has('mypint'))->toBeTrue();
});

test('it reports there is nothing to forget when no aliases exist and the argument is omitted', function () {
    $this->useIsolatedComposerHome();

    $tester = forgetCommandTester();
    $status = $tester->execute([]);

    expect($status)->toBe(0)
        ->and($tester->getDisplay())->toContain('You have no aliases to forget.');
});

test('it reports there is nothing to forget when no aliases exist even if a name is given', function () {
    $this->useIsolatedComposerHome();

    $tester = forgetCommandTester();
    $status = $tester->execute(['name' => 'mypint']);

    expect($status)->toBe(0)
        ->and($tester->getDisplay())->toContain('You have no aliases to forget.');
});
