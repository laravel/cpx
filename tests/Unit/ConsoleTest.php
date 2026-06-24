<?php

use Cpx\Console;

test('it parses the command and positional arguments from argv tokens', function () {
    $console = Console::parse(['laravel/pint', '--test', 'app', 'two words'], flagOptions: ['test']);

    expect($console->command)->toBe('laravel/pint')
        ->and($console->arguments)->toBe(['app', 'two words'])
        ->and($console->hasFlag('test'))->toBeTrue();
});

test('it keeps repeated option values in order', function () {
    $console = Console::parse(['tool', '--filter=one', '--filter=two']);

    expect($console->options['filter'])->toBe(['one', 'two'])
        ->and($console->getOption('filter'))->toBe('one');
});

test('it maps configured short options and flags', function () {
    $console = Console::parse(
        ['tool', '-v', '-c', 'phpstan.neon'],
        shortOptions: ['c' => 'configuration', 'v' => 'verbose'],
        flagOptions: ['verbose'],
    );

    expect($console->hasFlag('verbose'))->toBeTrue()
        ->and($console->getOption('configuration'))->toBe('phpstan.neon');
});

test('the double-dash separator preserves all following target arguments')->todo(
    'Enable when the process runner forwards argv tokens without shell reconstruction.',
);

test('child process exit codes are returned through the command runner')->todo(
    'Enable when child process exit codes are propagated through the command runner.',
);
