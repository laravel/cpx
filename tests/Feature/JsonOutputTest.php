<?php

use Cpx\Composer\ComposerRunner;
use Cpx\Packages\Package;
use Cpx\Packages\UserAliases;
use Cpx\Support\Interactivity;

/**
 * @param  list<string>  $arguments
 * @return array{0: int, 1: array<string, mixed>, 2: string}
 */
function runCpxJsonCommand(array $arguments): array
{
    [$status, $output] = runCpxCommand($arguments);

    return [$status, json_decode($output, true, 512, JSON_THROW_ON_ERROR), $output];
}

test('installed outputs the package list as json', function () {
    $this->useIsolatedComposerHome();

    mkdir(dirname(cpx_path('.cpx_metadata.json')), 0755, true);
    file_put_contents(cpx_path('.cpx_metadata.json'), json_encode([
        'packages' => [
            'laravel/pint' => ['last_updated' => '2024-01-02 03:04:05', 'last_run' => '2024-01-02 03:04:05'],
        ],
        'execCache' => [],
    ], JSON_THROW_ON_ERROR));

    [$status, $payload] = runCpxJsonCommand(['installed', '--json']);

    expect($status)->toBe(0)
        ->and($payload)->toBe([
            'success' => true,
            'errors' => [],
            'summary' => [
                'packages' => [
                    ['name' => 'laravel/pint', 'last_run' => '2024-01-02 03:04:05'],
                ],
            ],
        ]);
});

test('installed outputs an empty package list as json', function () {
    $this->useIsolatedComposerHome();

    [$status, $payload] = runCpxJsonCommand(['installed', '--json']);

    expect($status)->toBe(0)
        ->and($payload)->toBe(['success' => true, 'errors' => [], 'summary' => ['packages' => []]]);
});

test('installed reports a never-run package with a null last_run', function () {
    $this->useIsolatedComposerHome();

    mkdir(dirname(cpx_path('.cpx_metadata.json')), 0755, true);
    file_put_contents(cpx_path('.cpx_metadata.json'), json_encode([
        'packages' => [
            'laravel/pint' => ['last_updated' => null, 'last_run' => null],
        ],
        'execCache' => [],
    ], JSON_THROW_ON_ERROR));

    [$status, $payload] = runCpxJsonCommand(['installed', '--json']);

    expect($status)->toBe(0)
        ->and($payload['summary']['packages'])->toBe([
            ['name' => 'laravel/pint', 'last_run' => null],
        ]);
});

test('non-interactive runs output json without the flag', function () {
    $this->useIsolatedComposerHome();
    Interactivity::fake(false);

    [$status, $payload] = runCpxJsonCommand(['installed']);

    expect($status)->toBe(0)
        ->and($payload['success'])->toBeTrue();
});

test('json output is a single line', function () {
    $this->useIsolatedComposerHome();

    [, , $output] = runCpxJsonCommand(['installed', '--json']);

    expect(str_ends_with($output, PHP_EOL))->toBeTrue()
        ->and(rtrim($output, PHP_EOL))->not->toContain("\n");
});

test('aliases outputs the alias map as json', function () {
    $this->useIsolatedComposerHome();
    UserAliases::open()->put('mypint', Package::parse('laravel/pint'))->save();

    [$status, $payload] = runCpxJsonCommand(['aliases', '--json']);

    expect($status)->toBe(0)
        ->and($payload)->toBe([
            'success' => true,
            'errors' => [],
            'summary' => ['aliases' => ['mypint' => 'laravel/pint']],
        ]);
});

test('an empty alias map is written as a json object', function () {
    $this->useIsolatedComposerHome();

    [$status, , $output] = runCpxJsonCommand(['aliases', '--json']);

    expect($status)->toBe(0)
        ->and($output)->toContain('"aliases":{}');
});

test('alias creates an alias and reports it as json', function () {
    $this->useIsolatedComposerHome();
    prepareCachedPackage('laravel/pint', ['pint']);

    [$status, $payload] = runCpxJsonCommand(['alias', 'laravel/pint', 'mypint', '--json']);

    expect($status)->toBe(0)
        ->and($payload)->toBe([
            'success' => true,
            'errors' => [],
            'summary' => ['alias' => 'mypint', 'package' => 'laravel/pint'],
        ])
        ->and(UserAliases::open()->find('mypint')?->fullPackageString())->toBe('laravel/pint');
});

test('alias refuses to overwrite an existing alias without force when non-interactive', function () {
    $this->useIsolatedComposerHome();
    prepareCachedPackage('vendor/two', ['two']);
    UserAliases::open()->put('tool', Package::parse('vendor/one'))->save();
    Interactivity::fake(false);

    [$status, $payload] = runCpxJsonCommand(['alias', 'vendor/two', 'tool']);

    expect($status)->toBe(1)
        ->and($payload['success'])->toBeFalse()
        ->and($payload['errors'])->toBe(['The alias "tool" already exists. Use the --force option to overwrite it.'])
        ->and(UserAliases::open()->find('tool')?->fullPackageString())->toBe('vendor/one');
});

test('alias overwrites an existing alias with force when non-interactive', function () {
    $this->useIsolatedComposerHome();
    prepareCachedPackage('vendor/two', ['two']);
    UserAliases::open()->put('tool', Package::parse('vendor/one'))->save();
    Interactivity::fake(false);

    [$status, $payload] = runCpxJsonCommand(['alias', 'vendor/two', 'tool', '--force']);

    expect($status)->toBe(0)
        ->and($payload['success'])->toBeTrue()
        ->and(UserAliases::open()->find('tool')?->fullPackageString())->toBe('vendor/two');
});

test('alias reports multiple binaries as a json failure mentioning the bin option', function () {
    $this->useIsolatedComposerHome();
    prepareCachedPackage('vendor/multi', ['one', 'two']);

    [$status, $payload] = runCpxJsonCommand(['alias', 'vendor/multi', 'multi', '--json']);

    expect($status)->toBe(1)
        ->and($payload['success'])->toBeFalse()
        ->and($payload['errors'][0])->toContain('--bin')
        ->and(UserAliases::open()->has('multi'))->toBeFalse();
});

test('alias reports a missing package argument as a json failure', function () {
    $this->useIsolatedComposerHome();

    [$status, $payload] = runCpxJsonCommand(['alias', '--json']);

    expect($status)->toBe(1)
        ->and($payload['success'])->toBeFalse()
        ->and($payload['errors'])->toBe(['A package name must be provided.'])
        ->and(UserAliases::open()->all())->toBe([]);
});

test('unalias removes an alias and reports it as json', function () {
    $this->useIsolatedComposerHome();
    UserAliases::open()->put('mypint', Package::parse('laravel/pint'))->save();

    [$status, $payload] = runCpxJsonCommand(['unalias', 'mypint', '--json']);

    expect($status)->toBe(0)
        ->and($payload)->toBe([
            'success' => true,
            'errors' => [],
            'summary' => ['removed' => 'mypint'],
        ])
        ->and(UserAliases::open()->has('mypint'))->toBeFalse();
});

test('unalias reports an unknown alias as a json failure', function () {
    $this->useIsolatedComposerHome();
    UserAliases::open()->put('mypint', Package::parse('laravel/pint'))->save();

    [$status, $payload] = runCpxJsonCommand(['unalias', 'nope', '--json']);

    expect($status)->toBe(1)
        ->and($payload['success'])->toBeFalse()
        ->and($payload['errors'])->toBe(['No alias named "nope" was found.']);
});

test('unalias reports an unknown alias as a json failure when no aliases are saved', function () {
    $this->useIsolatedComposerHome();

    [$status, $payload] = runCpxJsonCommand(['unalias', 'nope', '--json']);

    expect($status)->toBe(1)
        ->and($payload['success'])->toBeFalse()
        ->and($payload['errors'])->toBe(['No alias named "nope" was found.']);
});

test('unalias reports a missing name as a json failure when no aliases are saved', function () {
    $this->useIsolatedComposerHome();

    [$status, $payload] = runCpxJsonCommand(['unalias', '--json']);

    expect($status)->toBe(1)
        ->and($payload['success'])->toBeFalse()
        ->and($payload['errors'])->toBe(['An alias name must be provided.']);
});

test('clean reports removals as json', function () {
    $this->useIsolatedComposerHome();

    [$status, $payload] = runCpxJsonCommand(['clean', '--days', '30', '--json']);

    expect($status)->toBe(0)
        ->and($payload)->toBe(['success' => true, 'errors' => [], 'summary' => ['removed' => []]]);
});

test('clean without options uses its defaults when non-interactive', function () {
    $this->useIsolatedComposerHome();
    Interactivity::fake(false);

    [$status, $payload] = runCpxJsonCommand(['clean']);

    expect($status)->toBe(0)
        ->and($payload['success'])->toBeTrue();
});

test('clean rejects an invalid days option as a json failure', function () {
    $this->useIsolatedComposerHome();

    [$status, $payload] = runCpxJsonCommand(['clean', '--days', 'soon', '--json']);

    expect($status)->toBe(2)
        ->and($payload)->toBe([
            'success' => false,
            'errors' => ['The --days option must be a positive integer.'],
            'summary' => [],
        ]);
});

test('update reports package updates as json', function () {
    $this->useIsolatedComposerHome();

    [$status, $payload] = runCpxJsonCommand(['update', '--json']);

    expect($status)->toBe(0)
        ->and($payload)->toBe(['success' => true, 'errors' => [], 'summary' => ['packages' => []]]);
});

test('update flags an unchanged package as not updated in json', function () {
    $this->useIsolatedComposerHome();
    prepareCachedPackage('laravel/pint', ['pint']);
    file_put_contents(cpx_path('laravel/pint/latest/composer.lock'), json_encode([
        'packages' => [['name' => 'laravel/pint', 'version' => 'v1.0.0']],
    ], JSON_THROW_ON_ERROR));

    $calls = [];
    fakeComposer($calls);

    [$status, $payload] = runCpxJsonCommand(['update', '--json']);

    expect($status)->toBe(0)
        ->and($payload['summary']['packages'])->toBe([
            ['package' => 'laravel/pint/latest', 'updated' => false, 'from' => 'v1.0.0', 'to' => 'v1.0.0', 'reason' => 'already up-to-date'],
        ]);
});

test('update reports a failed package update as a json failure', function () {
    $this->useIsolatedComposerHome();
    prepareCachedPackage('laravel/pint', ['pint']);
    file_put_contents(cpx_path('laravel/pint/latest/composer.lock'), json_encode([
        'packages' => [['name' => 'laravel/pint', 'version' => 'v1.0.0']],
    ], JSON_THROW_ON_ERROR));

    $calls = [];
    fakeComposer($calls, 1);

    [$status, $payload] = runCpxJsonCommand(['update', '--json']);

    expect($status)->toBe(1)
        ->and($payload['success'])->toBeFalse()
        ->and($payload['errors'])->toBe(['laravel/pint/latest: Composer command failed: update'])
        ->and($payload['summary']['packages'])->toBe([
            ['package' => 'laravel/pint/latest', 'updated' => false, 'from' => 'v1.0.0', 'to' => 'v1.0.0', 'reason' => 'Composer command failed: update'],
        ]);
});

test('update flags an upgraded package as updated in json', function () {
    $this->useIsolatedComposerHome();
    prepareCachedPackage('laravel/pint', ['pint']);
    file_put_contents(cpx_path('laravel/pint/latest/composer.lock'), json_encode([
        'packages' => [['name' => 'laravel/pint', 'version' => 'v1.0.0']],
    ], JSON_THROW_ON_ERROR));

    ComposerRunner::fake(function (array $command): int {
        foreach ($command as $argument) {
            if (str_starts_with($argument, '--working-dir=')) {
                $directory = substr($argument, strlen('--working-dir='));
                file_put_contents("{$directory}/composer.lock", json_encode([
                    'packages' => [['name' => 'laravel/pint', 'version' => 'v2.0.0']],
                ], JSON_THROW_ON_ERROR));
            }
        }

        return 0;
    });

    [$status, $payload] = runCpxJsonCommand(['update', '--json']);

    expect($status)->toBe(0)
        ->and($payload['summary']['packages'])->toBe([
            ['package' => 'laravel/pint/latest', 'updated' => true, 'from' => 'v1.0.0', 'to' => 'v2.0.0', 'reason' => null],
        ]);
});

test('update reports an invalid target as a json failure', function () {
    $this->useIsolatedComposerHome();

    [$status, $payload] = runCpxJsonCommand(['update', 'not-a-valid//package', '--json']);

    expect($status)->toBe(1)
        ->and($payload['success'])->toBeFalse()
        ->and($payload['errors'])->toBe(['A package name should be in the format "<vendor>/<package>[:version]".']);
});

test('console errors are reported as a json failure when non-interactive', function () {
    Interactivity::fake(false);

    [$status, $payload] = runCpxJsonCommand(['clean', '--bogus']);

    expect($status)->toBe(1)
        ->and($payload['success'])->toBeFalse()
        ->and($payload['errors'][0])->toContain('--bogus');
});

test('run reports an unrecognised command as a json failure when non-interactive', function () {
    $this->useIsolatedComposerHome();
    $this->useWorkingDirectory($this->temporaryDirectory('cpx-project'));
    Interactivity::fake(false);

    [$status, $payload] = runCpxJsonCommand(['totally-unknown-tool']);

    expect($status)->toBe(1)
        ->and($payload['success'])->toBeFalse()
        ->and($payload['errors'])->toBe(['Unrecognised command totally-unknown-tool']);
});

test('exec reports a missing file as a json failure when non-interactive', function () {
    Interactivity::fake(false);

    [$status, $payload] = runCpxJsonCommand(['exec', 'definitely-missing-file.php']);

    expect($status)->toBe(1)
        ->and($payload['success'])->toBeFalse()
        ->and($payload['errors'])->toBe(["File does not exist at 'definitely-missing-file.php'"]);
});

test('exec reports a missing target as a json failure when non-interactive', function () {
    Interactivity::fake(false);

    [$status, $payload] = runCpxJsonCommand(['exec']);

    expect($status)->toBe(1)
        ->and($payload['success'])->toBeFalse()
        ->and($payload['errors'])->toBe(['Please supply the path to a file to execute.']);
});

test('successful package runs stream raw child output when non-interactive', function () {
    $this->useIsolatedComposerHome();
    Interactivity::fake(false);

    prepareCachedPackage('vendor/tool', ['tool'], [
        'tool' => "#!/usr/bin/env php\n<?php exit(7);\n",
    ]);

    [$status, $output] = runCpxCommand(['vendor/tool']);

    expect($status)->toBe(7)
        ->and($output)->not->toContain('"success"');
});

test('run reports a package that cannot be installed as a json failure when non-interactive', function () {
    $this->useIsolatedComposerHome();
    $this->useWorkingDirectory($this->temporaryDirectory('cpx-project'));
    Interactivity::fake(false);

    $calls = [];
    fakeComposer($calls, 1);

    [$status, $payload] = runCpxJsonCommand(['vendor/missing']);

    expect($status)->toBe(1)
        ->and($payload['success'])->toBeFalse()
        ->and($payload['errors'])->toBe(['The package "vendor/missing" could not be found.']);
});

test('run reports a package without binaries as a json failure when non-interactive', function () {
    $this->useIsolatedComposerHome();
    Interactivity::fake(false);
    prepareCachedPackage('vendor/nobin', []);

    [$status, $payload] = runCpxJsonCommand(['vendor/nobin']);

    expect($status)->toBe(1)
        ->and($payload['success'])->toBeFalse()
        ->and($payload['errors'])->toBe(['No bin command found in vendor/nobin.']);
});

test('run reports ambiguous binaries as a json failure when non-interactive', function () {
    $this->useIsolatedComposerHome();
    Interactivity::fake(false);
    prepareCachedPackage('vendor/multi', ['one', 'two'], [
        'one' => noopBinary(),
        'two' => noopBinary(),
    ]);

    [$status, $payload] = runCpxJsonCommand(['vendor/multi']);

    expect($status)->toBe(1)
        ->and($payload['success'])->toBeFalse()
        ->and($payload['errors'][0])->toContain('More than 1 bin command found');
});

test('run reports a missing binary file as a json failure when non-interactive', function () {
    $this->useIsolatedComposerHome();
    Interactivity::fake(false);
    prepareCachedPackage('vendor/ghost', ['ghost']);

    [$status, $payload] = runCpxJsonCommand(['vendor/ghost']);

    expect($status)->toBe(1)
        ->and($payload['success'])->toBeFalse()
        ->and($payload['errors'])->toBe(['Command ghost not found in vendor/ghost.']);
});

test('run reports a local package without binaries as a json failure when non-interactive', function () {
    $this->useIsolatedComposerHome();
    Interactivity::fake(false);
    $root = $this->prepareLocalPackage([], 'vendor/local');

    [$status, $payload] = runCpxJsonCommand([$root]);

    expect($status)->toBe(1)
        ->and($payload['success'])->toBeFalse()
        ->and($payload['errors'])->toBe(["No bin command found in {$root}."]);
});

test('non-interactive package runs omit cpx progress output', function () {
    $this->useIsolatedComposerHome();
    Interactivity::fake(false);
    prepareCachedPackage('vendor/tool', ['tool'], ['tool' => noopBinary()]);

    [$status, $output] = runCpxCommand(['vendor/tool']);

    expect($status)->toBe(0)
        ->and($output)->toBe('');
});

test('interactive runs keep the human output', function () {
    $this->useIsolatedComposerHome();

    [$status, $output] = runCpxCommand(['installed']);

    expect($status)->toBe(0)
        ->and($output)->toContain('There are no installed packages.');
});
