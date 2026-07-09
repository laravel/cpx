<?php

use Cpx\Application;
use Cpx\Input\PackageInvocation;
use Cpx\Packages\Package;
use Cpx\Packages\PackageCommandRunner;
use Cpx\Packages\UserAliases;
use Cpx\Runtime\Environment;
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
    [$status, $output] = runCpxCommand(['list', '--help']);

    expect($status)->toBe(0)
        ->and($output)->toContain('List installed cpx packages')
        ->and($output)->toContain('Usage:');
});

test('empty invocations run the default list command', function () {
    $this->useIsolatedComposerHome();

    [$status, $output] = runCpxCommand([]);

    expect($status)->toBe(0)
        ->and($output)->toContain('There are no installed packages.');
});

test('list shows when no packages are installed', function () {
    $this->useIsolatedComposerHome();

    [$status, $output] = runCpxCommand(['list']);

    expect($status)->toBe(0)
        ->and($output)->toContain('There are no installed packages.')
        ->and($output)->not->toContain('Available commands');
});

test('list renders installed packages with their last run timestamp', function () {
    $this->useIsolatedComposerHome();

    mkdir(dirname(cpx_path('.cpx_metadata.json')), 0755, true);
    file_put_contents(cpx_path('.cpx_metadata.json'), json_encode([
        'packages' => [
            'laravel/pint' => ['last_updated' => '2024-01-02 03:04:05', 'last_run' => '2024-01-02 03:04:05'],
        ],
        'execCache' => [],
    ], JSON_THROW_ON_ERROR));

    [$status, $output] = runCpxCommand(['list']);

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

    [$status] = runCpxCommand(['update']);

    expect($status)->toBe(0)
        ->and($calls)->toHaveCount(1)
        ->and($calls[0][0])->toBe('update')
        ->and($calls[0])->toContain('--working-dir='.cpx_path('laravel/pint/latest'));
});

test('exec runs inline php code', function () {
    $directory = $this->temporaryDirectory('cpx-exec');
    $this->useWorkingDirectory($directory);

    [$status, $output] = runCpxCommand(['exec', '-r', 'echo "hello";']);

    expect($status)->toBe(0)
        ->and($output)->toContain('hello');
});

test('file fallback preserves exec options', function () {
    $directory = $this->temporaryDirectory('cpx-file-fallback');
    $this->useWorkingDirectory($directory);

    mkdir($directory.'/vendor', 0755, true);
    file_put_contents($directory.'/vendor/autoload.php', '<?php $GLOBALS[\'cpx_autoload_loaded\'] = true;');
    file_put_contents($directory.'/script.php', '<?php echo isset($GLOBALS[\'cpx_autoload_loaded\']) ? \'loaded\' : \'not-loaded\';');

    [$status, $output] = runCpxCommand(['script.php', '--find-autoloader=false']);

    expect($status)->toBe(0)
        ->and($output)->toContain('not-loaded');
});

test('tinker runs the cached psysh package with the bundled config', function () {
    $this->useIsolatedComposerHome();

    $packageDirectory = cpx_path('psy/psysh/latest/vendor/psy/psysh');
    mkdir($packageDirectory, 0755, true);

    file_put_contents(cpx_path('psy/psysh/latest/vendor/autoload.php'), '<?php');
    file_put_contents($packageDirectory.'/composer.json', json_encode([
        'bin' => ['psysh'],
    ], JSON_THROW_ON_ERROR));
    writeExecutable($packageDirectory.'/psysh', "#!/usr/bin/env php\n<?php exit(0);\n");
    file_put_contents(cpx_path('.cpx_metadata.json'), json_encode([
        'packages' => [
            'psy/psysh' => [
                'last_updated' => date('Y-m-d H:i:s'),
                'last_run' => date('Y-m-d H:i:s'),
            ],
        ],
        'execCache' => [],
    ], JSON_THROW_ON_ERROR));

    [$status, $output] = runCpxCommand(['tinker']);

    expect($status)->toBe(0)
        ->and($output)->toContain('Running psysh from psy/psysh');
});

test('tinker materializes the bundled config outside the phar for the psysh child process', function () {
    $this->useIsolatedComposerHome();
    Environment::fakePharPath('/opt/cpx.phar');

    $packageDirectory = cpx_path('psy/psysh/latest/vendor/psy/psysh');
    mkdir($packageDirectory, 0755, true);

    file_put_contents(cpx_path('psy/psysh/latest/vendor/autoload.php'), '<?php');
    file_put_contents($packageDirectory.'/composer.json', json_encode([
        'bin' => ['psysh'],
    ], JSON_THROW_ON_ERROR));
    writeExecutable($packageDirectory.'/psysh', "#!/usr/bin/env php\n<?php exit(0);\n");
    file_put_contents(cpx_path('.cpx_metadata.json'), json_encode([
        'packages' => [
            'psy/psysh' => [
                'last_updated' => date('Y-m-d H:i:s'),
                'last_run' => date('Y-m-d H:i:s'),
            ],
        ],
        'execCache' => [],
    ], JSON_THROW_ON_ERROR));

    [$status] = runCpxCommand(['tinker']);

    expect($status)->toBe(0)
        ->and((string) file_get_contents(cpx_path('psysh-config.php')))
        ->toContain("Phar::loadPhar('/opt/cpx.phar', 'cpx.phar')")
        ->toContain("return require 'phar://cpx.phar/files/psysh-config.php';");
});

test('unknown package targets route to the package fallback command', function () {
    $runner = new class extends PackageCommandRunner
    {
        public ?PackageInvocation $invocation = null;

        public function run(PackageInvocation $invocation, OutputInterface $output): int
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

        public function run(PackageInvocation $invocation, OutputInterface $output): int
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

        public function run(PackageInvocation $invocation, OutputInterface $output): int
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
