<?php

use Cpx\Runtime\PromptFallbacks;
use Laravel\Prompts\Exceptions\NonInteractiveValidationException;
use Laravel\Prompts\Prompt;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\number;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

function enableFallbackPrompts(string $script, bool $enabled = true, bool $interactive = true): BufferedOutput
{
    $input = new ArrayInput([]);
    $input->setInteractive($interactive);

    $stream = fopen('php://memory', 'r+');
    fwrite($stream, $script);
    rewind($stream);
    $input->setStream($stream);

    $output = new BufferedOutput;

    PromptFallbacks::register($input, $output);
    Prompt::fallbackWhen($enabled);

    return $output;
}

test('select falls back to a choice question and returns the value for list options', function () {
    enableFallbackPrompts("phpstan\n");

    $choice = select(label: 'Which binary?', options: ['pint', 'phpstan']);

    expect($choice)->toBe('phpstan');
});

test('select returns the key for keyed options and honours the default', function () {
    enableFallbackPrompts("\n");

    $choice = select(
        label: 'What would you like to clean?',
        options: ['all' => 'Everything', 'period' => 'Packages older than a number of days'],
        default: 'period',
    );

    expect($choice)->toBe('period');
});

test('confirm maps the answer to a boolean and honours the default', function () {
    enableFallbackPrompts("n\n");

    expect(confirm(label: 'Overwrite the alias?', default: true))->toBeFalse();

    enableFallbackPrompts("\n");

    expect(confirm(label: 'Overwrite the alias?', default: true))->toBeTrue();
});

test('text returns the entered value', function () {
    enableFallbackPrompts("my-alias\n");

    expect(text(label: 'What should the alias be named?'))->toBe('my-alias');
});

test('text returns the default on empty input', function () {
    enableFallbackPrompts("\n");

    expect(text(label: 'What should the alias be named?', default: 'pint'))->toBe('pint');
});

test('text re-prompts until validation passes', function () {
    enableFallbackPrompts("bad\ngood\n");

    $promptOutput = new BufferedOutput;
    Prompt::setOutput($promptOutput);

    $value = text(
        label: 'Name?',
        validate: fn (string $value): ?string => $value === 'bad' ? 'Not that one.' : null,
    );

    expect($value)->toBe('good')
        ->and($promptOutput->fetch())->toContain('Not that one.');
});

test('text re-prompts until required input is given', function () {
    enableFallbackPrompts("\nmy-alias\n");

    $promptOutput = new BufferedOutput;
    Prompt::setOutput($promptOutput);

    $value = text(label: 'Name?', required: 'A name is required.');

    expect($value)->toBe('my-alias')
        ->and($promptOutput->fetch())->toContain('A name is required.');
});

test('number enforces the minimum before returning the value', function () {
    enableFallbackPrompts("0\n7\n");

    $promptOutput = new BufferedOutput;
    Prompt::setOutput($promptOutput);

    $days = number(label: 'Remove packages older than how many days?', default: '30', min: 1);

    expect($days)->toBe('7')
        ->and($promptOutput->fetch())->toContain('Must be at least 1');
});

test('number returns the default on empty input', function () {
    enableFallbackPrompts("\n");

    expect(number(label: 'Remove packages older than how many days?', default: '30', min: 1))->toBe('30');
});

test('non-interactive fallbacks return the default without prompting', function () {
    enableFallbackPrompts("phpstan\n", interactive: false);

    expect(text(label: 'Name?', default: 'pint'))->toBe('pint');

    enableFallbackPrompts("all\n", interactive: false);

    expect(select(
        label: 'What would you like to clean?',
        options: ['all' => 'Everything', 'period' => 'Packages older than a number of days'],
        default: 'period',
    ))->toBe('period');
});

test('non-interactive required prompts throw instead of looping', function () {
    enableFallbackPrompts('', interactive: false);

    text(label: 'Name?', required: 'A name is required.');
})->throws(NonInteractiveValidationException::class, 'A name is required.');

test('a non-interactive select with no default throws the required error', function () {
    enableFallbackPrompts('', interactive: false);

    select(label: 'Which binary?', options: ['pint', 'phpstan']);
})->throws(NonInteractiveValidationException::class, 'Required.');

test('non-interactive validation failures throw instead of looping', function () {
    enableFallbackPrompts('', interactive: false);

    text(
        label: 'Name?',
        default: 'bad',
        validate: fn (string $value): ?string => $value === 'bad' ? 'Not that one.' : null,
    );
})->throws(NonInteractiveValidationException::class, 'Not that one.');

test('registered fallbacks stay inert until enabled', function () {
    enableFallbackPrompts("phpstan\n", enabled: false);

    $choice = select(label: 'Which binary?', options: ['pint', 'phpstan'], default: 'pint');

    expect($choice)->toBe('pint');
});
