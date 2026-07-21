<?php

use Cpx\Application;
use Cpx\Composer\ComposerRunner;
use Cpx\Input\PackageInvocation;
use Cpx\Packages\PackageCommandRunner;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Tests\TestCase;

pest()->extend(TestCase::class)->in('Feature', 'Unit');

/**
 * @param  list<string>  $arguments
 * @return array{0: int, 1: string}
 */
function runCpxCommand(array $arguments): array
{
    $application = new Application;
    $output = new BufferedOutput;
    $status = $application->run(new ArgvInput(['cpx', ...$arguments]), $output);

    return [$status, $output->fetch()];
}

/**
 * @param  array<string, mixed>  $aliases
 */
function writeAliasesFile(array $aliases): void
{
    $file = cpx_path('aliases.json');

    if (! is_dir(dirname($file))) {
        mkdir(dirname($file), 0755, true);
    }

    file_put_contents($file, json_encode($aliases, JSON_THROW_ON_ERROR));
}

/**
 * A PackageCommandRunner that records the invocation on a public property instead of running it.
 */
function recordingPackageRunner(): PackageCommandRunner
{
    return new class extends PackageCommandRunner
    {
        public ?PackageInvocation $invocation = null;

        public function run(PackageInvocation $invocation, OutputInterface $output, bool $skipLocal = false): int
        {
            $this->invocation = $invocation;

            return 0;
        }
    };
}

function writeExecutable(string $path, string $contents): void
{
    file_put_contents($path, $contents);
    chmod($path, 0755);
}

/** Windows only allows symlink creation with Developer Mode or elevation */
function canCreateSymlinks(): bool
{
    static $supported = null;

    if ($supported !== null) {
        return $supported;
    }

    $target = sys_get_temp_dir().'/cpx-symlink-probe-'.bin2hex(random_bytes(4));
    $link = "{$target}-link";

    mkdir($target, 0755, true);
    $supported = @symlink($target, $link);

    @unlink($link);
    @rmdir($link);
    @rmdir($target);

    return $supported;
}

/**
 * A PHP binary that logs its forwarded argv tokens to $logFile and exits with $exitCode.
 */
function argvLoggingBinary(string $logFile, int $exitCode = 0): string
{
    return "#!/usr/bin/env php\n<?php file_put_contents(".var_export($logFile, true).", json_encode(array_slice(\$argv, 1), JSON_THROW_ON_ERROR)); exit({$exitCode});\n";
}

/**
 * A PHP binary that does nothing and exits with $exitCode.
 */
function noopBinary(int $exitCode = 0): string
{
    return "#!/usr/bin/env php\n<?php exit({$exitCode});\n";
}

/**
 * Fake the in-process Composer runner; record each call's argv and write an autoloader on success.
 *
 * @param  list<list<string>>  $calls
 */
function fakeComposer(array &$calls, int $exitCode = 0): void
{
    ComposerRunner::fake(function (array $command) use (&$calls, $exitCode): int {
        $calls[] = $command;

        if ($exitCode === 0) {
            foreach ($command as $argument) {
                if (str_starts_with($argument, '--working-dir=')) {
                    $directory = substr($argument, strlen('--working-dir='));
                    @mkdir("{$directory}/vendor", 0755, true);
                    file_put_contents("{$directory}/vendor/autoload.php", '<?php');
                }
            }
        }

        return $exitCode;
    });
}

/**
 * @param  list<string>  $bins
 * @param  array<string, string>  $executables
 */
function prepareCachedPackage(string $package, array $bins, array $executables = []): string
{
    [$vendor, $name] = explode('/', $package);
    $packageDirectory = cpx_path("{$vendor}/{$name}/latest/vendor/{$vendor}/{$name}");

    mkdir($packageDirectory, 0755, true);
    file_put_contents($packageDirectory.'/composer.json', json_encode(['bin' => $bins], JSON_THROW_ON_ERROR));
    file_put_contents(cpx_path("{$vendor}/{$name}/latest/vendor/autoload.php"), '<?php');

    foreach ($executables as $bin => $contents) {
        writeExecutable("{$packageDirectory}/{$bin}", $contents);
    }

    file_put_contents(cpx_path('.cpx_metadata.json'), json_encode([
        'packages' => [
            $package => [
                'last_updated' => date('Y-m-d H:i:s'),
                'last_run' => date('Y-m-d H:i:s'),
            ],
        ],
        'execCache' => [],
    ], JSON_THROW_ON_ERROR));

    return $packageDirectory;
}
