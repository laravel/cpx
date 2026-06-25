<?php

use Cpx\Application;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\BufferedOutput;

test('it removes all tracked package and exec cache directories', function () {
    $this->useIsolatedComposerHome();

    $packageDirectory = cpx_path('laravel/pint/latest');
    $execDirectory = cpx_path('.exec_cache/sandbox');

    mkdir($packageDirectory, 0755, true);
    mkdir($execDirectory, 0755, true);

    if (! is_dir(dirname(cpx_path('.cpx_metadata.json')))) {
        mkdir(dirname(cpx_path('.cpx_metadata.json')), 0755, true);
    }
    file_put_contents(cpx_path('.cpx_metadata.json'), json_encode([
        'packages' => [
            'laravel/pint' => [
                'last_updated' => '2024-01-01 00:00:00',
                'last_run' => '2024-01-01 00:00:00',
            ],
        ],
        'execCache' => [
            'sandbox' => [
                'packages' => ['laravel/pint'],
                'last_updated' => 1,
                'last_run' => 1,
            ],
        ],
    ], JSON_THROW_ON_ERROR));

    (new Application)->run(new ArgvInput(['cpx', 'clean', '--all']), new BufferedOutput);

    expect(is_dir($packageDirectory))->toBeFalse()
        ->and(is_dir($execDirectory))->toBeFalse()
        ->and(json_decode((string) file_get_contents(cpx_path('.cpx_metadata.json')), true))->toBe([
            'packages' => [],
            'execCache' => [],
        ]);
});

test('it preserves fresh tracked package cache directories', function () {
    $this->useIsolatedComposerHome();

    $packageDirectory = cpx_path('laravel/pint/latest');

    mkdir($packageDirectory, 0755, true);

    if (! is_dir(dirname(cpx_path('.cpx_metadata.json')))) {
        mkdir(dirname(cpx_path('.cpx_metadata.json')), 0755, true);
    }
    file_put_contents(cpx_path('.cpx_metadata.json'), json_encode([
        'packages' => [
            'laravel/pint' => [
                'last_updated' => date('Y-m-d H:i:s'),
                'last_run' => date('Y-m-d H:i:s'),
            ],
        ],
        'execCache' => [],
    ], JSON_THROW_ON_ERROR));

    (new Application)->run(new ArgvInput(['cpx', 'clean']), new BufferedOutput);

    expect(is_dir($packageDirectory))->toBeTrue();
});

test('orphaned package directories are detected and cleaned')->todo(
    'Enable when cleanup scans for cache directories missing from metadata.',
);

test('cleanup refuses to delete paths outside the cpx cache root')->todo(
    'Enable when cleanup validates every deletion stays inside the cpx cache root.',
);
