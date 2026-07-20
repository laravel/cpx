<?php

use Cpx\Application;
use Cpx\Composer\ComposerRunner;
use Cpx\Input\PackageInvocation;
use Cpx\Packages\Package;
use Cpx\Packages\PackageCommandRunner;
use Cpx\Packages\UserAliases;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\ApplicationTester;

test('it can run through Symfony tester utilities without exiting', function () {
    $tester = new ApplicationTester(new Application);

    $status = $tester->run(['command' => 'help']);

    expect($status)->toBe(0)
        ->and($tester->getDisplay())->toContain('Usage:');
});

test('command help options use Symfony command help', function () {
    [$status, $output] = runCpxCommand(['installed', '--help']);

    expect($status)->toBe(0)
        ->and($output)->toContain('List installed cpx packages')
        ->and($output)->toContain('Usage:');
});

test('empty invocations run the default list command', function () {
    $this->useIsolatedComposerHome();

    [$status, $output] = runCpxCommand([]);

    expect($status)->toBe(0)
        ->and($output)->toContain('Available commands')
        ->and($output)->not->toContain('There are no installed packages.');
});

test('installed shows when no packages are installed', function () {
    $this->useIsolatedComposerHome();

    [$status, $output] = runCpxCommand(['installed']);

    expect($status)->toBe(0)
        ->and($output)->toContain('There are no installed packages.')
        ->and($output)->not->toContain('Available commands');
});

test('list shows the available cpx commands', function () {
    $this->useIsolatedComposerHome();

    [$status, $output] = runCpxCommand(['list']);

    expect($status)->toBe(0)
        ->and($output)->toContain('Available commands')
        ->and($output)->toContain('installed')
        ->and($output)->not->toContain('There are no installed packages.');
});

test('installed renders installed packages with their last run timestamp', function () {
    $this->useIsolatedComposerHome();

    mkdir(dirname(cpx_path('.cpx_metadata.json')), 0755, true);
    file_put_contents(cpx_path('.cpx_metadata.json'), json_encode([
        'packages' => [
            'laravel/pint' => ['last_updated' => '2024-01-02 03:04:05', 'last_run' => '2024-01-02 03:04:05'],
        ],
        'execCache' => [],
    ], JSON_THROW_ON_ERROR));

    [$status, $output] = runCpxCommand(['installed']);

    expect($status)->toBe(0)
        ->and($output)->toContain('Installed Packages:')
        ->and($output)->toContain('laravel/pint')
        ->and($output)->toContain('Last Run: 2024-01-02 03:04:05');
});

test('aliases reports when no aliases have been created', function () {
    $this->useIsolatedComposerHome();

    [$status, $output] = runCpxCommand(['aliases']);

    expect($status)->toBe(0)
        ->and($output)->toContain('You have no aliases.');
});

test('aliases lists user-defined aliases', function () {
    $this->useIsolatedComposerHome();

    UserAliases::open()->put('mypint', Package::parse('laravel/pint'))->save();

    [$status, $output] = runCpxCommand(['aliases']);

    expect($status)->toBe(0)
        ->and($output)->toContain('Your aliases:')
        ->and($output)->toContain('cpx mypint')
        ->and($output)->toContain('laravel/pint');
});

test('a user-defined alias resolves to its package', function () {
    $this->useIsolatedComposerHome();

    $packageDirectory = prepareCachedPackage('vendor/custom-pint', ['pint']);
    writeExecutable($packageDirectory.'/pint', "#!/usr/bin/env php\n<?php exit(0);\n");
    UserAliases::open()->put('pint', Package::parse('vendor/custom-pint'))->save();

    [$status, $output] = runCpxCommand(['pint']);

    expect($status)->toBe(0)
        ->and($output)->toContain('Running pint from vendor/custom-pint');
});

test('unalias removes a user-defined alias', function () {
    $this->useIsolatedComposerHome();

    $packageDirectory = prepareCachedPackage('vendor/custom-pint', ['pint']);
    writeExecutable($packageDirectory.'/pint', "#!/usr/bin/env php\n<?php exit(0);\n");
    UserAliases::open()->put('pint', Package::parse('vendor/custom-pint'))->save();

    [$unaliasStatus, $unaliasOutput] = runCpxCommand(['unalias', 'pint']);

    expect($unaliasStatus)->toBe(0)
        ->and($unaliasOutput)->toContain('Alias "pint" removed.')
        ->and(UserAliases::open()->has('pint'))->toBeFalse();
});

test('clean reports when there are no packages to clean', function () {
    $this->useIsolatedComposerHome();

    [$status, $output] = runCpxCommand(['clean']);

    expect($status)->toBe(0)
        ->and($output)->toContain('Nothing to clean');
});

test('update reports when there are no packages to update', function () {
    $this->useIsolatedComposerHome();

    [$status, $output] = runCpxCommand(['update']);

    expect($status)->toBe(0)
        ->and($output)->toContain('There are no packages to update.');
});

test('update requests a composer update for each installed package directory', function () {
    $this->useIsolatedComposerHome();

    prepareCachedPackage('laravel/pint', ['pint']);

    $calls = [];
    fakeComposer($calls);

    [$status, $output] = runCpxCommand(['update']);

    expect($status)->toBe(0)
        ->and($output)->toContain('Updating laravel/pint/latest')
        ->and($calls)->toHaveCount(1)
        ->and($calls[0][0])->toBe('update')
        ->and($calls[0])->toContain('--working-dir='.cpx_path('laravel/pint/latest'));
});

test('update continues past a failing package and summarizes the failure', function () {
    $this->useIsolatedComposerHome();

    prepareCachedPackage('vendor/aaa', ['aaa']);
    prepareCachedPackage('vendor/bbb', ['bbb']);

    $calls = [];
    ComposerRunner::fake(function (array $command) use (&$calls): int {
        $calls[] = $command;

        return str_contains(implode(' ', $command), 'vendor/aaa') ? 1 : 0;
    });

    [$status, $output] = runCpxCommand(['update']);

    expect($status)->toBe(1)
        ->and($calls)->toHaveCount(2)
        ->and($output)->toContain('Could not update')
        ->and($output)->toContain('vendor/aaa/latest: Composer command failed: update');
});

test('bare php file targets are rejected with an exec hint', function () {
    $this->useIsolatedComposerHome();
    $directory = $this->temporaryDirectory('cpx-bare-file');
    $this->useWorkingDirectory($directory);

    file_put_contents($directory.'/script.php', '<?php echo "ran";');

    [$status, $output] = runCpxCommand(['script.php']);

    expect($status)->toBe(1)
        ->and($output)->toContain('Unrecognised command script.php')
        ->and($output)->toContain('To run a PHP file, use: cpx exec script.php')
        ->and($output)->not->toContain('ran');
});

test('bare existing files without a php extension also get the exec hint', function () {
    $this->useIsolatedComposerHome();
    $directory = $this->temporaryDirectory('cpx-bare-file');
    $this->useWorkingDirectory($directory);

    file_put_contents($directory.'/runme', '<?php echo "ran";');

    [$status, $output] = runCpxCommand(['runme']);

    expect($status)->toBe(1)
        ->and($output)->toContain('Unrecognised command runme')
        ->and($output)->toContain('To run a PHP file, use: cpx exec runme');
});

test('unknown package targets route to the package fallback command', function () {
    $runner = new class extends PackageCommandRunner
    {
        public ?PackageInvocation $invocation = null;

        public function run(PackageInvocation $invocation, OutputInterface $output, bool $skipLocal = false): int
        {
            $this->invocation = $invocation;

            return 0;
        }
    };
    $application = new Application($runner);

    $status = $application->run(new ArgvInput(['cpx', 'vendor/package', '--flag', 'value']), new BufferedOutput);

    expect($status)->toBe(0)
        ->and($runner->invocation)->toBeInstanceOf(PackageInvocation::class)
        ->and($runner->invocation?->target)->toBe('vendor/package')
        ->and($runner->invocation?->forwardedTokens())->toBe(['--flag', 'value']);
});

test('package fallback accepts arbitrary package options without Symfony validation errors', function () {
    $runner = new class extends PackageCommandRunner
    {
        public ?PackageInvocation $invocation = null;

        public function run(PackageInvocation $invocation, OutputInterface $output, bool $skipLocal = false): int
        {
            $this->invocation = $invocation;

            return 0;
        }
    };
    $application = new Application($runner);

    $status = $application->run(new ArgvInput([
        'cpx',
        'vendor/package',
        '--unknown',
        'value',
        '-x',
        '--filter=one',
        '--filter=two',
        '--',
        '--literal',
    ]), new BufferedOutput);

    expect($status)->toBe(0)
        ->and($runner->invocation?->target)->toBe('vendor/package')
        ->and($runner->invocation?->forwardedTokens())->toBe([
            '--unknown',
            'value',
            '-x',
            '--filter=one',
            '--filter=two',
            '--',
            '--literal',
        ]);
});

test('package-target version options are forwarded instead of rendering cpx version', function () {
    $runner = new class extends PackageCommandRunner
    {
        public ?PackageInvocation $invocation = null;

        public function run(PackageInvocation $invocation, OutputInterface $output, bool $skipLocal = false): int
        {
            $this->invocation = $invocation;

            return 0;
        }
    };
    $application = new Application($runner);
    $output = new BufferedOutput;

    $status = $application->run(new ArgvInput(['cpx', 'pint', '--version']), $output);

    expect($status)->toBe(0)
        ->and($runner->invocation?->target)->toBe('pint')
        ->and($runner->invocation?->forwardedTokens())->toBe(['--version'])
        ->and($output->fetch())->not->toContain('cpx version:');
});

test('package-looking values with shell metacharacters fail before composer execution', function () {
    $this->useIsolatedComposerHome();

    $calls = [];
    fakeComposer($calls);

    [$status, $output] = runCpxCommand(['vendor/package;touch injected']);

    expect($status)->toBe(1)
        ->and($output)->toContain('Unrecognised command vendor/package;touch injected')
        ->and($calls)->toBe([]);
});

test('invalid fallback commands return a failure status with help output', function () {
    $this->useIsolatedComposerHome();

    [$status, $output] = runCpxCommand(['not-a-package']);

    expect($status)->toBe(1)
        ->and($output)->toContain('Unrecognised command not-a-package');
});

test('a package that composer cannot install renders a package-not-found error', function () {
    $this->useIsolatedComposerHome();

    $calls = [];
    fakeComposer($calls, exitCode: 1);

    [$status, $output] = runCpxCommand(['foo/bar']);

    expect($status)->toBe(1)
        ->and($output)->toContain('Package not found')
        ->and($output)->toContain('Composer was unable to install')
        ->and($output)->toContain('`foo/bar`')
        ->and($output)->toContain('spelled correctly')
        ->and($output)->toContain('https://packagist.org/packages/foo/bar')
        ->and($output)->not->toContain('__cpx_run_package');
});

test('a package-not-found error mentions the requested version constraint', function () {
    $this->useIsolatedComposerHome();

    $calls = [];
    fakeComposer($calls, exitCode: 1);

    [$status, $output] = runCpxCommand(['foo/bar:^9.0']);

    expect($status)->toBe(1)
        ->and($output)->toContain('Package not found')
        ->and($output)->toContain('version matching')
        ->and($output)->toContain('`^9.0`')
        ->and($output)->toContain('https://packagist.org/packages/foo/bar');
});
