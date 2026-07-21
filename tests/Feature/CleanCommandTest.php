<?php

use Cpx\Cache\Metadata;
use Laravel\Prompts\Key;
use Laravel\Prompts\Prompt;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

uses(MockeryPHPUnitIntegration::class);

test('the all option removes every tracked package and exec cache directory', function () {
    $this->useIsolatedComposerHome();

    $packageDirectory = cpx_path('laravel/pint/latest');
    $execDirectory = cpx_path('.exec_cache/sandbox');

    mkdir($packageDirectory, 0755, true);
    mkdir($execDirectory, 0755, true);

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

    runCpxCommand(['clean', '--all']);

    expect(is_dir($packageDirectory))->toBeFalse()
        ->and(is_dir($execDirectory))->toBeFalse()
        ->and(json_decode((string) file_get_contents(cpx_path('.cpx_metadata.json')), true))->toBe([
            'version' => 2,
            'packages' => [],
            'execCache' => [],
        ]);
});

test('cleaning a package prunes the now-empty vendor directories', function () {
    $this->useIsolatedComposerHome();

    mkdir(cpx_path('laravel/pint/latest'), 0755, true);

    file_put_contents(cpx_path('.cpx_metadata.json'), json_encode([
        'packages' => [
            'laravel/pint' => [
                'last_updated' => '2024-01-01 00:00:00',
                'last_run' => '2024-01-01 00:00:00',
            ],
        ],
    ], JSON_THROW_ON_ERROR));

    runCpxCommand(['clean', '--all']);

    expect(is_dir(cpx_path('laravel/pint/latest')))->toBeFalse()
        ->and(is_dir(cpx_path('laravel/pint')))->toBeFalse()
        ->and(is_dir(cpx_path('laravel')))->toBeFalse();
});

test('the sandbox option removes exec caches but preserves package caches', function () {
    $this->useIsolatedComposerHome();

    $packageDirectory = cpx_path('laravel/pint/latest');
    mkdir($packageDirectory.'/vendor', 0755, true);
    file_put_contents($packageDirectory.'/vendor/autoload.php', '<?php');

    $sandboxDirectory = cpx_path('.exec_cache/sandbox');
    mkdir($sandboxDirectory, 0755, true);

    file_put_contents(cpx_path('.cpx_metadata.json'), json_encode([
        'packages' => [
            'laravel/pint' => [
                'last_updated' => date('Y-m-d H:i:s'),
                'last_run' => date('Y-m-d H:i:s'),
            ],
        ],
        'execCache' => [
            'sandbox' => ['packages' => ['laravel/pint'], 'last_updated' => time(), 'last_run' => time()],
        ],
    ], JSON_THROW_ON_ERROR));

    [$status] = runCpxCommand(['clean', '--sandbox']);

    expect($status)->toBe(0)
        ->and(is_dir($packageDirectory))->toBeTrue()
        ->and(Metadata::open()->hasPackage('laravel/pint'))->toBeTrue()
        ->and(is_dir($sandboxDirectory))->toBeFalse()
        ->and(Metadata::open()->execCache)->toBe([]);
});

test('the sandbox option with a days window removes only sandboxes older than the window', function () {
    $this->useIsolatedComposerHome();

    $packageDirectory = cpx_path('laravel/pint/latest');
    mkdir($packageDirectory.'/vendor', 0755, true);
    file_put_contents($packageDirectory.'/vendor/autoload.php', '<?php');

    $oldSandbox = cpx_path('.exec_cache/old-sandbox');
    $freshSandbox = cpx_path('.exec_cache/fresh-sandbox');
    mkdir($oldSandbox, 0755, true);
    mkdir($freshSandbox, 0755, true);

    file_put_contents(cpx_path('.cpx_metadata.json'), json_encode([
        'packages' => [
            'laravel/pint' => ['last_updated' => '2024-01-01 00:00:00', 'last_run' => '2024-01-01 00:00:00'],
        ],
        'execCache' => [
            'old-sandbox' => ['packages' => ['laravel/pint'], 'last_updated' => time() - 100 * 86400, 'last_run' => time() - 100 * 86400],
            'fresh-sandbox' => ['packages' => ['laravel/pint'], 'last_updated' => time(), 'last_run' => time()],
        ],
    ], JSON_THROW_ON_ERROR));

    [$status] = runCpxCommand(['clean', '--sandbox', '--days=90']);

    expect($status)->toBe(0)
        ->and(is_dir($oldSandbox))->toBeFalse()
        ->and(is_dir($freshSandbox))->toBeTrue()
        ->and(is_dir($packageDirectory))->toBeTrue()
        ->and(Metadata::open()->hasPackage('laravel/pint'))->toBeTrue()
        ->and(array_keys(Metadata::open()->execCache))->toBe(['fresh-sandbox']);
});

test('the all option with a days window applies it to packages and sandboxes', function () {
    $this->useIsolatedComposerHome();

    $oldPackage = cpx_path('laravel/pint/latest');
    $freshPackage = cpx_path('phpunit/phpunit/latest');
    mkdir($oldPackage.'/vendor', 0755, true);
    file_put_contents($oldPackage.'/vendor/autoload.php', '<?php');
    mkdir($freshPackage.'/vendor', 0755, true);
    file_put_contents($freshPackage.'/vendor/autoload.php', '<?php');

    $oldSandbox = cpx_path('.exec_cache/old-sandbox');
    $freshSandbox = cpx_path('.exec_cache/fresh-sandbox');
    mkdir($oldSandbox, 0755, true);
    mkdir($freshSandbox, 0755, true);

    file_put_contents(cpx_path('.cpx_metadata.json'), json_encode([
        'packages' => [
            'laravel/pint' => ['last_updated' => date('Y-m-d H:i:s', time() - 10 * 86400), 'last_run' => date('Y-m-d H:i:s', time() - 10 * 86400)],
            'phpunit/phpunit' => ['last_updated' => date('Y-m-d H:i:s'), 'last_run' => date('Y-m-d H:i:s')],
        ],
        'execCache' => [
            'old-sandbox' => ['packages' => ['laravel/pint'], 'last_updated' => time() - 10 * 86400, 'last_run' => time() - 10 * 86400],
            'fresh-sandbox' => ['packages' => ['laravel/pint'], 'last_updated' => time(), 'last_run' => time()],
        ],
    ], JSON_THROW_ON_ERROR));

    [$status] = runCpxCommand(['clean', '--all', '--days=7']);

    expect($status)->toBe(0)
        ->and(is_dir($oldPackage))->toBeFalse()
        ->and(is_dir($freshPackage))->toBeTrue()
        ->and(is_dir($oldSandbox))->toBeFalse()
        ->and(is_dir($freshSandbox))->toBeTrue()
        ->and(Metadata::open()->hasPackage('laravel/pint'))->toBeFalse()
        ->and(Metadata::open()->hasPackage('phpunit/phpunit'))->toBeTrue();
});

test('the all option without a days window still removes fresh caches', function () {
    $this->useIsolatedComposerHome();

    $packageDirectory = cpx_path('laravel/pint/latest');
    mkdir($packageDirectory.'/vendor', 0755, true);
    file_put_contents($packageDirectory.'/vendor/autoload.php', '<?php');

    $sandboxDirectory = cpx_path('.exec_cache/sandbox');
    mkdir($sandboxDirectory, 0755, true);

    file_put_contents(cpx_path('.cpx_metadata.json'), json_encode([
        'packages' => [
            'laravel/pint' => ['last_updated' => date('Y-m-d H:i:s'), 'last_run' => date('Y-m-d H:i:s')],
        ],
        'execCache' => [
            'sandbox' => ['packages' => ['laravel/pint'], 'last_updated' => time(), 'last_run' => time()],
        ],
    ], JSON_THROW_ON_ERROR));

    [$status] = runCpxCommand(['clean', '--all']);

    expect($status)->toBe(0)
        ->and(is_dir($packageDirectory))->toBeFalse()
        ->and(is_dir($sandboxDirectory))->toBeFalse();
});

test('the sandbox option is a no-op when there are no sandboxes', function () {
    $this->useIsolatedComposerHome();

    [$status, $output] = runCpxCommand(['clean', '--sandbox']);

    expect($status)->toBe(0)
        ->and($output)->toContain('Nothing to clean');
});

test('a days window of zero reclaims everything older than now', function () {
    $this->useIsolatedComposerHome();

    $packageDirectory = cpx_path('laravel/pint/latest');
    mkdir($packageDirectory.'/vendor', 0755, true);
    file_put_contents($packageDirectory.'/vendor/autoload.php', '<?php');

    file_put_contents(cpx_path('.cpx_metadata.json'), json_encode([
        'packages' => [
            'laravel/pint' => [
                'last_updated' => '2024-01-01 00:00:00',
                'last_run' => '2024-01-01 00:00:00',
            ],
        ],
        'execCache' => [],
    ], JSON_THROW_ON_ERROR));

    [$status] = runCpxCommand(['clean', '--days=0']);

    expect($status)->toBe(0)
        ->and(is_dir($packageDirectory))->toBeFalse()
        ->and(Metadata::open()->hasPackage('laravel/pint'))->toBeFalse();
});

test('a long days window preserves recently active packages', function () {
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

    [$status] = runCpxCommand(['clean', '--days=3650']);

    expect($status)->toBe(0)
        ->and(is_dir($packageDirectory))->toBeTrue()
        ->and(Metadata::open()->hasPackage('laravel/pint'))->toBeTrue();
});

test('an invalid days value is rejected before any cleanup runs', function () {
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

    [$status, $output] = runCpxCommand(['clean', '--days=abc']);

    expect($status)->not->toBe(0)
        ->and($output)->toContain('positive integer')
        ->and(is_dir($packageDirectory))->toBeTrue();
});

test('a negative days value is rejected before any cleanup runs', function () {
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

    [$status, $output] = runCpxCommand(['clean', '--days=-5']);

    expect($status)->not->toBe(0)
        ->and($output)->toContain('positive integer')
        ->and(is_dir($packageDirectory))->toBeTrue();
});

test('a bare clean preserves fresh tracked package cache directories', function () {
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

    runCpxCommand(['clean']);

    expect(is_dir($packageDirectory))->toBeTrue();
});

test('a bare clean preserves a freshly installed package that was never run', function () {
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

test('a bare clean reclaims a package that was installed long ago and never run', function () {
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

    [$status, $output] = runCpxCommand(['clean']);

    expect($status)->toBe(1)
        ->and(is_dir($outside))->toBeTrue()
        ->and(file_exists($outside.'/keep.txt'))->toBeTrue()
        ->and($output)->toContain('Could not remove');
})->skip(! canCreateSymlinks(), 'symlink creation is unavailable (Windows without Developer Mode)');

test('the summary lists the caches that were removed', function () {
    $this->useIsolatedComposerHome();

    $packageDirectory = cpx_path('laravel/pint/latest');
    mkdir($packageDirectory.'/vendor', 0755, true);
    file_put_contents($packageDirectory.'/vendor/autoload.php', '<?php');

    file_put_contents(cpx_path('.cpx_metadata.json'), json_encode([
        'packages' => [
            'laravel/pint' => ['last_updated' => '2024-01-01 00:00:00', 'last_run' => '2024-01-01 00:00:00'],
        ],
        'execCache' => [],
    ], JSON_THROW_ON_ERROR));

    [$status, $output] = runCpxCommand(['clean']);

    expect($status)->toBe(0)
        ->and($output)
        ->toContain('Clean Summary')
        ->toContain('Removed')
        ->toContain('laravel/pint');
});

test('the interactive prompt shows the available clean options', function () {
    $this->useIsolatedComposerHome();

    Prompt::fake([Key::ENTER, Key::ENTER]);

    [, $output] = runCpxCommand(['clean']);

    expect($output)
        ->toContain('What would you like to clean?')
        ->toContain('All cached packages and sandboxes')
        ->toContain('Only sandbox (exec) caches')
        ->toContain('Packages older than a number of days');
})->skipOnWindows();

test('choosing "all" interactively cleans every package and sandbox', function () {
    $this->useIsolatedComposerHome();

    $packageDirectory = cpx_path('laravel/pint/latest');
    $execDirectory = cpx_path('.exec_cache/sandbox');
    mkdir($packageDirectory.'/vendor', 0755, true);
    file_put_contents($packageDirectory.'/vendor/autoload.php', '<?php');
    mkdir($execDirectory, 0755, true);

    file_put_contents(cpx_path('.cpx_metadata.json'), json_encode([
        'packages' => [
            'laravel/pint' => ['last_updated' => date('Y-m-d H:i:s'), 'last_run' => date('Y-m-d H:i:s')],
        ],
        'execCache' => [
            'sandbox' => ['packages' => ['laravel/pint'], 'last_updated' => time(), 'last_run' => time()],
        ],
    ], JSON_THROW_ON_ERROR));

    // From the "period" default, move up twice to "all", then confirm.
    Prompt::fake([Key::UP, Key::UP, Key::ENTER]);

    runCpxCommand(['clean']);

    expect(is_dir($packageDirectory))->toBeFalse()
        ->and(is_dir($execDirectory))->toBeFalse()
        ->and(Metadata::open()->packages)->toBe([])
        ->and(Metadata::open()->execCache)->toBe([]);
})->skipOnWindows();

test('choosing "sandbox" interactively preserves package caches', function () {
    $this->useIsolatedComposerHome();

    $packageDirectory = cpx_path('laravel/pint/latest');
    mkdir($packageDirectory.'/vendor', 0755, true);
    file_put_contents($packageDirectory.'/vendor/autoload.php', '<?php');

    $sandboxDirectory = cpx_path('.exec_cache/sandbox');
    mkdir($sandboxDirectory, 0755, true);

    file_put_contents(cpx_path('.cpx_metadata.json'), json_encode([
        'packages' => [
            'laravel/pint' => ['last_updated' => date('Y-m-d H:i:s'), 'last_run' => date('Y-m-d H:i:s')],
        ],
        'execCache' => [
            'sandbox' => ['packages' => ['laravel/pint'], 'last_updated' => time(), 'last_run' => time()],
        ],
    ], JSON_THROW_ON_ERROR));

    // From the "period" default, move up once to "sandbox", then confirm.
    Prompt::fake([Key::UP, Key::ENTER]);

    runCpxCommand(['clean']);

    expect(is_dir($packageDirectory))->toBeTrue()
        ->and(Metadata::open()->hasPackage('laravel/pint'))->toBeTrue()
        ->and(is_dir($sandboxDirectory))->toBeFalse()
        ->and(Metadata::open()->execCache)->toBe([]);
})->skipOnWindows();

test('choosing "period" interactively cleans by the entered number of days', function () {
    $this->useIsolatedComposerHome();

    $idle = cpx_path('laravel/pint/latest');
    mkdir($idle.'/vendor', 0755, true);
    file_put_contents($idle.'/vendor/autoload.php', '<?php');

    $recent = cpx_path('phpunit/phpunit/latest');
    mkdir($recent.'/vendor', 0755, true);
    file_put_contents($recent.'/vendor/autoload.php', '<?php');

    file_put_contents(cpx_path('.cpx_metadata.json'), json_encode([
        'packages' => [
            'laravel/pint' => ['last_updated' => date('Y-m-d H:i:s', time() - 10 * 86400), 'last_run' => date('Y-m-d H:i:s', time() - 10 * 86400)],
            'phpunit/phpunit' => ['last_updated' => date('Y-m-d H:i:s', time() - 3 * 86400), 'last_run' => date('Y-m-d H:i:s', time() - 3 * 86400)],
        ],
        'execCache' => [],
    ], JSON_THROW_ON_ERROR));

    // Confirm the "period" default, clear the prefilled "30", then enter "7".
    Prompt::fake([Key::ENTER, Key::BACKSPACE, Key::BACKSPACE, '7', Key::ENTER]);

    runCpxCommand(['clean']);

    expect(is_dir($idle))->toBeFalse()
        ->and(Metadata::open()->hasPackage('laravel/pint'))->toBeFalse()
        ->and(is_dir($recent))->toBeTrue()
        ->and(Metadata::open()->hasPackage('phpunit/phpunit'))->toBeTrue();
})->skipOnWindows();

test('the interactive number prompt rejects values below one and re-prompts', function () {
    $this->useIsolatedComposerHome();

    $idle = cpx_path('laravel/pint/latest');
    mkdir($idle.'/vendor', 0755, true);
    file_put_contents($idle.'/vendor/autoload.php', '<?php');

    file_put_contents(cpx_path('.cpx_metadata.json'), json_encode([
        'packages' => [
            'laravel/pint' => ['last_updated' => date('Y-m-d H:i:s', time() - 10 * 86400), 'last_run' => date('Y-m-d H:i:s', time() - 10 * 86400)],
        ],
        'execCache' => [],
    ], JSON_THROW_ON_ERROR));

    // Confirm "period", clear "30", submit invalid "0" (rejected), then enter "5".
    Prompt::fake([Key::ENTER, Key::BACKSPACE, Key::BACKSPACE, '0', Key::ENTER, Key::BACKSPACE, '5', Key::ENTER]);

    [, $output] = runCpxCommand(['clean']);

    expect($output)->toContain('Must be at least 1')
        ->and(is_dir($idle))->toBeFalse()
        ->and(Metadata::open()->hasPackage('laravel/pint'))->toBeFalse();
})->skipOnWindows();
