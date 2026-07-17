<?php

use Cpx\Support\JsonEnvelope;
use Symfony\Component\Console\Output\BufferedOutput;

test('success envelopes have the standard shape', function () {
    $envelope = JsonEnvelope::success(['packages' => []]);

    expect($envelope->success)->toBeTrue()
        ->and($envelope->errors)->toBe([])
        ->and($envelope->summary)->toBe(['packages' => []]);
});

test('failure envelopes normalize a single error to a list', function () {
    $envelope = JsonEnvelope::failure('Something broke.');

    expect($envelope->success)->toBeFalse()
        ->and($envelope->errors)->toBe(['Something broke.'])
        ->and($envelope->summary)->toBe([]);
});

test('failure envelopes keep error lists and summaries', function () {
    $envelope = JsonEnvelope::failure(['first', 'second'], ['removed' => ['a']]);

    expect($envelope->success)->toBeFalse()
        ->and($envelope->errors)->toBe(['first', 'second'])
        ->and($envelope->summary)->toBe(['removed' => ['a']]);
});

test('envelopes are written as a single raw json line with unescaped slashes', function () {
    $output = new BufferedOutput;

    JsonEnvelope::success(['package' => 'laravel/pint'])->write($output);

    expect($output->fetch())->toBe('{"success":true,"errors":[],"summary":{"package":"laravel/pint"}}'.PHP_EOL);
});

test('an empty summary is written as a json object', function () {
    $output = new BufferedOutput;

    JsonEnvelope::failure('Boom')->write($output);

    expect($output->fetch())->toBe('{"success":false,"errors":["Boom"],"summary":{}}'.PHP_EOL);
});

test('failure envelopes are written with their errors and summary', function () {
    $output = new BufferedOutput;

    JsonEnvelope::failure('Boom', ['removed' => []])->write($output);

    expect($output->fetch())->toBe('{"success":false,"errors":["Boom"],"summary":{"removed":[]}}'.PHP_EOL);
});
