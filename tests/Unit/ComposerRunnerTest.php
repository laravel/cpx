<?php

declare(strict_types=1);

use Cpx\Composer\ComposerRunner;
use Cpx\Exceptions\ComposerCommandException;
use Cpx\Exceptions\PackageNotFoundException;
use Cpx\Process\ProcessResult;
use Cpx\Process\ProcessRunner;
use Cpx\Support\SilentLogger;
use Symfony\Component\Console\Output\BufferedOutput;

test('it runs the require command', function () {
    $captured = null;
    ComposerRunner::fake(function (array $command) use (&$captured): int {
        $captured = $command;

        return 0;
    });

    $exitCode = ComposerRunner::require('vendor/package:^1.0', '/tmp/example');

    expect($exitCode)->toBe(0)
        ->and($captured)->toBe([
            'require',
            'vendor/package:^1.0',
            '--no-interaction',
            '--working-dir=/tmp/example',
        ]);
});

test('it assembles arguments with no-interaction and the working-dir option', function () {
    $captured = null;
    ComposerRunner::fake(function (array $command) use (&$captured): int {
        $captured = $command;

        return 0;
    });

    ComposerRunner::run(['require', 'vendor/package:^1@dev', '--no-progress'], '/tmp/example dir');

    expect($captured)->toBe([
        'require',
        'vendor/package:^1@dev',
        '--no-progress',
        '--no-interaction',
        '--working-dir=/tmp/example dir',
    ]);
});

test('it omits the working-dir option when no directory is given', function () {
    $captured = null;
    ComposerRunner::fake(function (array $command) use (&$captured): int {
        $captured = $command;

        return 0;
    });

    ComposerRunner::run(['update']);

    expect($captured)->toBe(['update', '--no-interaction']);
});

test('it throws a uniform message when the runner reports a failure', function () {
    ComposerRunner::fake(fn (array $command): int => 12);

    ComposerRunner::run(['update']);
})->throws(ComposerCommandException::class, 'Composer command failed: update');

test('it identifies composer package discovery failures', function (string $requirement, string $diagnostic) {
    ComposerRunner::fake(fn (array $command): ProcessResult => new ProcessResult(1, $diagnostic));

    ComposerRunner::require($requirement, '/tmp/example');
})->with([
    'unversioned package' => ['vendor/missing', 'Could not find a matching version of package vendor/missing. Check the package spelling.'],
    'suggested package names' => ['vendor/missing', "Could not find package vendor/missing.\n\nDid you mean vendor/existing?"],
    'explicit version' => ['vendor/missing:^9.0', 'Root composer.json requires vendor/missing ^9.0, it could not be found in any version, there may be a typo in the package name.'],
    'ansi-decorated multiline output' => ['vendor/missing', "\e[31mRoot composer.json requires\e[39m\n vendor/missing, it could not be found in any version,\n there may be a typo in the package name."],
])->throws(PackageNotFoundException::class);

test('a package-not-found failure retains the requested constraint and composer exception', function () {
    ComposerRunner::fake(fn (array $command): ProcessResult => new ProcessResult(1, 'Could not find a matching version of package vendor/missing.'));

    try {
        ComposerRunner::require('vendor/missing:^9.0', '/tmp/example');
    } catch (PackageNotFoundException $exception) {
        expect($exception->package->fullPackageString())->toBe('vendor/missing:^9.0')
            ->and($exception->getPrevious())->toBeInstanceOf(ComposerCommandException::class);

        return;
    }

    $this->fail('Expected PackageNotFoundException to be thrown.');
});

test('it reports a missing dependency instead of the requested package', function () {
    ComposerRunner::fake(fn (array $command): ProcessResult => new ProcessResult(
        1,
        'vendor/package 1.0.0 requires dependency/missing * -> could not be found in any version, there may be a typo in the package name.',
    ));

    try {
        ComposerRunner::require('vendor/package:^1.0', '/tmp/example');
    } catch (PackageNotFoundException $exception) {
        expect($exception->package->fullPackageString())->toBe('dependency/missing');

        return;
    }

    $this->fail('Expected PackageNotFoundException to be thrown.');
});

test('it preserves other composer require failures', function (string $diagnostic) {
    ComposerRunner::fake(fn (array $command): ProcessResult => new ProcessResult(1, $diagnostic));

    ComposerRunner::require('vendor/package:^9.0', '/tmp/example');
})->with([
    'constraint mismatch' => ['Root composer.json requires vendor/package ^9.0, found vendor/package[1.0.0] but it does not match the constraint.'],
    'platform mismatch' => ['Package vendor/package has requirements incompatible with your PHP version, PHP extensions and Composer version.'],
    'transport failure' => ['curl error 60: SSL certificate problem'],
    'authentication failure' => ['Invalid credentials for https://repo.example.test/packages.json'],
    'unknown failure' => ['Composer encountered an unexpected error.'],
])->throws(ComposerCommandException::class, 'Composer command failed: require vendor/package:^9.0');

test('generic composer commands do not classify missing-package output', function () {
    ComposerRunner::fake(fn (array $command): ProcessResult => new ProcessResult(1, 'Could not find a matching version of package vendor/missing.'));

    ComposerRunner::run(['update'], '/tmp/example');
})->throws(ComposerCommandException::class, 'Composer command failed: update');

test('it identifies a real composer missing-package diagnostic', function () {
    $this->useIsolatedComposerHome();
    $staging = $this->stagingWithPathPackages(['cpx-fixture/available']);

    ProcessRunner::withLogger(
        new SilentLogger,
        fn () => ComposerRunner::require('cpx-fixture/missing:*', $staging),
    );
})->throws(PackageNotFoundException::class);

test('it identifies a real missing package whose long name wraps in the error output', function () {
    $this->useIsolatedComposerHome();
    $staging = $this->stagingWithPathPackages(['cpx-fixture/available']);

    ProcessRunner::withLogger(
        new SilentLogger,
        fn () => ComposerRunner::require('cpx-fixture/a-really-long-missing-package-name-that-composer-wraps', $staging),
    );
})->throws(PackageNotFoundException::class);

test('it identifies a real missing transitive dependency', function () {
    $this->useIsolatedComposerHome();

    $fixture = $this->temporaryDirectory('cpx-fixture');
    file_put_contents("{$fixture}/composer.json", json_encode([
        'name' => 'cpx-fixture/parent',
        'version' => '1.0.0',
        'require' => ['cpx-fixture/missing' => '*'],
    ], JSON_THROW_ON_ERROR));

    $staging = $this->temporaryDirectory('cpx-staging');
    file_put_contents("{$staging}/composer.json", json_encode([
        'repositories' => [
            ['type' => 'path', 'url' => $fixture, 'options' => ['symlink' => false]],
            ['packagist.org' => false],
        ],
    ], JSON_THROW_ON_ERROR));

    try {
        ProcessRunner::withLogger(
            new SilentLogger,
            fn () => ComposerRunner::require('cpx-fixture/parent:*', $staging),
        );
    } catch (PackageNotFoundException $exception) {
        expect($exception->package->fullPackageString())->toBe('cpx-fixture/missing');

        return;
    }

    $this->fail('Expected PackageNotFoundException to be thrown.');
});

test('it does not classify a real composer constraint failure as a missing package', function () {
    $this->useIsolatedComposerHome();
    $staging = $this->stagingWithPathPackages(['cpx-fixture/available']);

    ComposerRunner::require('cpx-fixture/available:^2.0', $staging);
})->throws(ComposerCommandException::class, 'Composer command failed: require cpx-fixture/available:^2.0');

test('it reads the locked version of the requested package by name', function () {
    $directory = $this->temporaryDirectory('cpx-lock');

    file_put_contents("{$directory}/composer.lock", json_encode([
        'packages' => [
            ['name' => 'clue/ndjson-react', 'version' => 'v1.3.0'],
            ['name' => 'friendsofphp/php-cs-fixer', 'version' => 'v3.64.0'],
        ],
    ], JSON_THROW_ON_ERROR));

    expect(ComposerRunner::getCurrentVersion($directory, 'friendsofphp/php-cs-fixer'))->toBe('v3.64.0')
        ->and(ComposerRunner::getCurrentVersion($directory, 'vendor/missing'))->toBe('unknown');
});

test('it reports an unknown version when the lock file is missing', function () {
    expect(ComposerRunner::getCurrentVersion($this->temporaryDirectory('cpx-no-lock'), 'vendor/package'))->toBe('unknown');
});

test('it boots composer in-process and returns the exit code', function () {
    $this->useIsolatedComposerHome();

    expect(ComposerRunner::runInProcess(['about', '--quiet'], new BufferedOutput))->toBe(0);
});

test('it returns a non-zero exit code when the booted composer command fails', function () {
    $this->useIsolatedComposerHome();

    expect(ComposerRunner::runInProcess(['this-command-does-not-exist', '--quiet'], new BufferedOutput))->toBe(1);
});

test('it runs an offline composer command in an isolated child process', function () {
    $this->useIsolatedComposerHome();

    ComposerRunner::run(['about', '--quiet']);
})->throwsNoExceptions();

test('it throws the uniform message when an unknown composer command fails in the child', function () {
    $this->useIsolatedComposerHome();

    ComposerRunner::run(['this-command-does-not-exist', '--quiet']);
})->throws(ComposerCommandException::class, 'Composer command failed: this-command-does-not-exist');

test('it installs a package from a local path repository without network', function () {
    $this->useIsolatedComposerHome();

    $staging = $this->stagingWithPathPackages(['cpx-fixture/pkg']);

    ComposerRunner::run(['require', 'cpx-fixture/pkg:*', '--quiet'], $staging);

    expect(file_exists("{$staging}/vendor/autoload.php"))->toBeTrue()
        ->and(file_exists("{$staging}/vendor/cpx-fixture/pkg/composer.json"))->toBeTrue();
});

test('it leaves the calling process working directory untouched when a working-dir command fails', function () {
    $this->useIsolatedComposerHome();

    $before = getcwd();
    $staging = $this->temporaryDirectory('cpx-cwd');

    expect(fn () => ComposerRunner::run(['this-command-does-not-exist', '--quiet'], $staging))
        ->toThrow(ComposerCommandException::class);

    expect(getcwd())->toBe($before);
});

test('it installs multiple packages across isolated child processes', function () {
    $this->useIsolatedComposerHome();

    $staging = $this->stagingWithPathPackages(['cpx-fixture/one', 'cpx-fixture/two']);

    ComposerRunner::run(['require', 'cpx-fixture/one:*', '--quiet'], $staging);
    ComposerRunner::run(['require', 'cpx-fixture/two:*', '--quiet'], $staging);

    expect(file_exists("{$staging}/vendor/cpx-fixture/one/composer.json"))->toBeTrue()
        ->and(file_exists("{$staging}/vendor/cpx-fixture/two/composer.json"))->toBeTrue();
});

test('it activates package plugins in the child without loading them into the cpx process', function () {
    $this->useIsolatedComposerHome();

    ['staging' => $staging, 'package' => $package, 'pluginClass' => $pluginClass] = $this->stagingWithPluginPackage();

    ComposerRunner::run(['require', "{$package}:*", '--quiet'], $staging);

    expect(file_exists("{$staging}/vendor/{$package}/composer.json"))->toBeTrue()
        ->and(class_exists($pluginClass, autoload: false))->toBeFalse();
});

test('it runs global composer commands in the child against COMPOSER_HOME', function () {
    $composerHome = $this->useIsolatedComposerHome();

    ComposerRunner::run(['global', 'config', 'sort-packages', 'true', '--quiet']);

    expect(file_exists("{$composerHome}/composer.json"))->toBeTrue()
        ->and(json_decode((string) file_get_contents("{$composerHome}/composer.json"), true))
        ->toHaveKey('config.sort-packages');
});
