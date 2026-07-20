<?php

use Cpx\Support\Interactivity;
use Symfony\Component\Console\Input\ArgvInput;

test('an agent environment is non-interactive', function () {
    Interactivity::clearFake();
    $this->setEnvironmentVariable('AI_AGENT', 'test-agent');

    Interactivity::detect(new ArgvInput(['cpx', 'list']));

    expect(Interactivity::isInteractive())->toBeFalse();
});

test('an agent environment is detected without explicit detection', function () {
    Interactivity::clearFake();
    $this->setEnvironmentVariable('AI_AGENT', 'test-agent');

    expect(Interactivity::isInteractive())->toBeFalse();
});

test('the no-interaction flag wins over an interactive environment', function () {
    Interactivity::fake(true);

    Interactivity::detect(new ArgvInput(['cpx', 'list', '--no-interaction']));

    expect(Interactivity::isInteractive())->toBeFalse();
});

test('the short no-interaction flag wins over an interactive environment', function () {
    Interactivity::fake(true);

    Interactivity::detect(new ArgvInput(['cpx', 'list', '-n']));

    expect(Interactivity::isInteractive())->toBeFalse();
});

test('the json flag wins over an interactive environment', function () {
    Interactivity::fake(true);

    Interactivity::detect(new ArgvInput(['cpx', 'list', '--json']));

    expect(Interactivity::isInteractive())->toBeFalse();
});

test('a no-interaction flag after the token separator is ignored', function () {
    Interactivity::fake(true);

    Interactivity::detect(new ArgvInput(['cpx', 'run', '--', 'pint', '-n']));

    expect(Interactivity::isInteractive())->toBeTrue();
});

test('faking the environment overrides real detection', function () {
    $this->setEnvironmentVariable('AI_AGENT', 'test-agent');

    Interactivity::fake(true);
    expect(Interactivity::isInteractive())->toBeTrue();

    Interactivity::fake(false);
    expect(Interactivity::isInteractive())->toBeFalse();
});

test('clearing the fake restores real detection', function () {
    $this->setEnvironmentVariable('AI_AGENT', 'test-agent');
    Interactivity::fake(true);

    Interactivity::clearFake();

    expect(Interactivity::isInteractive())->toBeFalse();
});
