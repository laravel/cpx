<?php

use Cpx\Application;
use Cpx\Console;
use Cpx\PackageCommandRunner;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\ApplicationTester;

function runCpxCommand(array $arguments): array
{
    $application = new Application;
    $output = new BufferedOutput;
    $status = $application->run(new ArgvInput(['cpx', ...$arguments]), $output);

    return [$status, $output->fetch()];
}

function writeExecutable(string $path, string $contents): void
{
    file_put_contents($path, $contents);
    chmod($path, 0755);
}

test('help shows the cpx usage guide', function () {
    [$status, $output] = runCpxCommand(['help']);

    expect($status)->toBe(0)
        ->and($output)->toContain('cpx - A Composer package runner')
        ->and($output)->toContain('cpx <vendor/package[:version]> [args]');
});

test('it can run through Symfony tester utilities without exiting', function () {
    $tester = new ApplicationTester(new Application);

    $status = $tester->run(['command' => 'help']);

    expect($status)->toBe(0)
        ->and($tester->getDisplay())->toContain('cpx - A Composer package runner');
});

test('empty invocations show cpx help instead of Symfony command listings', function () {
    [$status, $output] = runCpxCommand([]);

    expect($status)->toBe(0)
        ->and($output)->toContain('cpx - A Composer package runner')
        ->and($output)->not->toContain('Available commands');
});

test('list shows when no packages are installed', function () {
    $this->useIsolatedComposerHome();

    [$status, $output] = runCpxCommand(['list']);

    expect($status)->toBe(0)
        ->and($output)->toContain('There are no installed packages.')
        ->and($output)->not->toContain('Available commands');
});

test('aliases lists aliased package commands', function () {
    [$status, $output] = runCpxCommand(['aliases']);

    expect($status)->toBe(0)
        ->and($output)->toContain('Aliased packages:')
        ->and($output)->toContain('cpx pint');
});

test('version prints cpx and php versions', function () {
    [$status, $output] = runCpxCommand(['version']);

    expect($status)->toBe(0)
        ->and($output)->toContain('cpx version:')
        ->and($output)->toContain('php version:');
});

test('top-level version options keep cpx version behavior', function (string $option) {
    [$status, $output] = runCpxCommand([$option]);

    expect($status)->toBe(0)
        ->and($output)->toContain('cpx version:');
})->with(['--version', '-v']);

test('clean reports when there are no packages to clean', function () {
    $this->useIsolatedComposerHome();

    [$status, $output] = runCpxCommand(['clean']);

    expect($status)->toBe(0)
        ->and($output)->toContain('There were no packages to clean.');
});

test('update reports when there are no packages to update', function () {
    $this->useIsolatedComposerHome();

    [$status, $output] = runCpxCommand(['update']);

    expect($status)->toBe(0)
        ->and($output)->toContain('There are no packages to update.');
});

test('upgrade runs the composer global update command', function () {
    $binDirectory = $this->temporaryDirectory('cpx-bin');
    $logFile = $this->temporaryDirectory('cpx-log').'/composer.log';

    writeExecutable($binDirectory.'/composer', "#!/usr/bin/env php\n<?php file_put_contents('{$logFile}', implode(' ', array_slice(\$argv, 1))); exit(0);\n");

    $this->setEnvironmentVariable('PATH', $binDirectory.PATH_SEPARATOR.getenv('PATH'));

    [$status, $output] = runCpxCommand(['upgrade']);

    expect($status)->toBe(0)
        ->and($output)->toContain('Updating')
        ->and(file_get_contents($logFile))->toContain('global update cpx/cpx');
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

test('format fails clearly when no formatter exists in the project', function (string $command) {
    $this->useWorkingDirectory($this->temporaryDirectory('cpx-format'));

    [$status, $output] = runCpxCommand([$command]);

    expect($status)->toBe(1)
        ->and($output)->toContain('No code formatters found in the project.');
})->with(['format', 'fmt']);

test('check fails clearly when no analyzer exists in the project', function (string $command) {
    $this->useWorkingDirectory($this->temporaryDirectory('cpx-check'));

    [$status, $output] = runCpxCommand([$command]);

    expect($status)->toBe(1)
        ->and($output)->toContain('No static analyzers found in the project.');
})->with(['check', 'analyze', 'analyse']);

test('test fails clearly when no test runner exists in the project', function () {
    $this->useWorkingDirectory($this->temporaryDirectory('cpx-test-runner'));

    [$status, $output] = runCpxCommand(['test']);

    expect($status)->toBe(1)
        ->and($output)->toContain('No test runner found in the project.');
});

test('tinker runs the cached psysh package with the bundled config', function () {
    $this->useIsolatedComposerHome();

    $packageDirectory = cpx_path('psy/psysh/latest/vendor/psy/psysh');
    mkdir($packageDirectory, 0755, true);

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

test('unknown package targets route to the package fallback command', function () {
    $runner = new class extends PackageCommandRunner
    {
        public ?Console $console = null;

        public function run(Console $console, OutputInterface $output): int
        {
            $this->console = $console;

            return 0;
        }
    };
    $application = new Application($runner);

    $status = $application->run(new ArgvInput(['cpx', 'vendor/package', '--flag', 'value']), new BufferedOutput);

    expect($status)->toBe(0)
        ->and($runner->console)->toBeInstanceOf(Console::class)
        ->and($runner->console?->command)->toBe('vendor/package')
        ->and($runner->console?->hasOption('flag'))->toBeTrue()
        ->and($runner->console?->getOption('flag'))->toBe('value');
});

test('package fallback accepts arbitrary package options without Symfony validation errors', function () {
    $runner = new class extends PackageCommandRunner
    {
        public ?Console $console = null;

        public function run(Console $console, OutputInterface $output): int
        {
            $this->console = $console;

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
        ->and($runner->console?->command)->toBe('vendor/package')
        ->and($runner->console?->getOption('unknown'))->toBe('value')
        ->and($runner->console?->options['filter'] ?? null)->toBe(['one', 'two']);
});

test('invalid fallback commands return a failure status with help output', function () {
    [$status, $output] = runCpxCommand(['not-a-package']);

    expect($status)->toBe(1)
        ->and($output)->toContain('Unrecognised command not-a-package');
});
