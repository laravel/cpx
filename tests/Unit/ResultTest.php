<?php

use Cpx\Support\Interactivity;
use Cpx\Support\Result;
use Laravel\Prompts\Output\BufferedConsoleOutput;
use Laravel\Prompts\Prompt;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\BufferedOutput;

test('success writes the json envelope', function () {
    $output = new BufferedOutput;

    $status = Result::success($output, ['removed' => ['laravel/pint']]);

    expect($status)->toBe(Command::SUCCESS)
        ->and($output->fetch())->toBe('{"success":true,"errors":[],"summary":{"removed":["laravel/pint"]}}'.PHP_EOL);
});

test('failure writes the json envelope when non-interactive', function () {
    Interactivity::fake(false);

    $output = new BufferedOutput;

    $status = Result::failure($output, 'Boom', ['removed' => []], Command::INVALID);

    expect($status)->toBe(Command::INVALID)
        ->and($output->fetch())->toBe('{"success":false,"errors":["Boom"],"summary":{"removed":[]}}'.PHP_EOL);
});

test('failure renders each error interactively without an envelope', function () {
    Interactivity::fake(true);
    Prompt::setOutput($promptOutput = new BufferedConsoleOutput);

    $output = new BufferedOutput;

    $status = Result::failure($output, ['First problem', 'Second problem']);

    $rendered = $promptOutput->fetch();

    expect($status)->toBe(Command::FAILURE)
        ->and($output->fetch())->toBe('')
        ->and($rendered)->toContain('First problem')
        ->and($rendered)->toContain('Second problem');
});
