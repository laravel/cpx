<?php

use Cpx\Application;
use Cpx\Cache\Metadata;
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
            'version' => 2,
            'aliases' => [],
            'packages' => [],
            'execCache' => [],
        ]);
});

test('it preserves fresh tracked package cache directories', function () {
    $this->useIsolatedComposerHome();

    $packageDirectory = cpx_path('laravel/pint/latest');

    mkdir($packageDirectory.'/vendor', 0755, true);
    file_put_contents($packageDirectory.'/vendor/autoload.php', '<?php');

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

test('it preserves a freshly installed package that was never run', function () {
    $this->useIsolatedComposerHome();

    $packageDirectory = cpx_path('laravel/pint/latest');

    mkdir($packageDirectory.'/vendor', 0755, true);
    file_put_contents($packageDirectory.'/vendor/autoload.php', '<?php');

    file_put_contents(cpx_path('.cpx_metadata.json'), json_encode([
        'packages' => [
            'laravel/pint' => [
                'last_updated' => date('Y-m-d H:i:s'),
                'last_run' => null,
            ],
        ],
        'execCache' => [],
    ], JSON_THROW_ON_ERROR));

    runCpxCommand(['clean']);

    expect(is_dir($packageDirectory))->toBeTrue()
        ->and(Metadata::open()->hasPackage('laravel/pint'))->toBeTrue();
});

test('it reclaims a package that was installed long ago and never run', function () {
    $this->useIsolatedComposerHome();

    $packageDirectory = cpx_path('laravel/pint/latest');

    mkdir($packageDirectory.'/vendor', 0755, true);
    file_put_contents($packageDirectory.'/vendor/autoload.php', '<?php');

    file_put_contents(cpx_path('.cpx_metadata.json'), json_encode([
        'packages' => [
            'laravel/pint' => [
                'last_updated' => '2024-01-01 00:00:00',
                'last_run' => null,
            ],
        ],
        'execCache' => [],
    ], JSON_THROW_ON_ERROR));

    runCpxCommand(['clean']);

    expect(is_dir($packageDirectory))->toBeFalse()
        ->and(Metadata::open()->hasPackage('laravel/pint'))->toBeFalse();
});

test('orphaned package directories are detected and cleaned', function () {
    $this->useIsolatedComposerHome();

    $orphan = cpx_path('orphan/package/latest');
    mkdir($orphan.'/vendor', 0755, true);
    file_put_contents($orphan.'/vendor/autoload.php', '<?php');
    file_put_contents(cpx_path('.cpx_metadata.json'), json_encode([
        'packages' => [],
        'execCache' => [],
    ], JSON_THROW_ON_ERROR));

    [$status, $output] = runCpxCommand(['clean']);

    expect($status)->toBe(0)
        ->and(is_dir($orphan))->toBeFalse()
        ->and($output)->toContain('orphan/package/latest');
});

test('incomplete install directories are treated as orphaned and removed', function () {
    $this->useIsolatedComposerHome();

    $incomplete = cpx_path('laravel/pint/latest');
    mkdir($incomplete.'/vendor', 0755, true);
    file_put_contents(cpx_path('.cpx_metadata.json'), json_encode([
        'packages' => [
            'laravel/pint' => [
                'last_updated' => date('Y-m-d H:i:s'),
                'last_run' => date('Y-m-d H:i:s'),
            ],
        ],
        'execCache' => [],
    ], JSON_THROW_ON_ERROR));

    runCpxCommand(['clean']);

    expect(is_dir($incomplete))->toBeFalse()
        ->and(Metadata::open()->hasPackage('laravel/pint'))->toBeFalse();
});

test('orphaned exec sandboxes are removed while tracked fresh sandboxes are preserved', function () {
    $this->useIsolatedComposerHome();

    mkdir(cpx_path('.exec_cache/orphan-key'), 0755, true);
    mkdir(cpx_path('.exec_cache/tracked-key'), 0755, true);
    file_put_contents(cpx_path('.cpx_metadata.json'), json_encode([
        'packages' => [],
        'execCache' => [
            'tracked-key' => ['packages' => ['laravel/pint'], 'last_updated' => time(), 'last_run' => time()],
        ],
    ], JSON_THROW_ON_ERROR));

    runCpxCommand(['clean']);

    expect(is_dir(cpx_path('.exec_cache/orphan-key')))->toBeFalse()
        ->and(is_dir(cpx_path('.exec_cache/tracked-key')))->toBeTrue();
});

test('cleanup refuses to delete paths outside the cpx cache root', function () {
    $this->useIsolatedComposerHome();

    $outside = $this->temporaryDirectory('cpx-outside');
    file_put_contents($outside.'/keep.txt', 'x');

    mkdir(cpx_path('.exec_cache'), 0755, true);
    symlink($outside, cpx_path('.exec_cache/escape'));
    file_put_contents(cpx_path('.cpx_metadata.json'), json_encode([
        'packages' => [],
        'execCache' => [],
    ], JSON_THROW_ON_ERROR));

    [$status] = runCpxCommand(['clean']);

    expect($status)->toBe(0)
        ->and(is_dir($outside))->toBeTrue()
        ->and(file_exists($outside.'/keep.txt'))->toBeTrue();
});
