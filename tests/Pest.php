<?php

use Cpx\Application;
use Cpx\Composer\ComposerRunner;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\BufferedOutput;
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

function writeExecutable(string $path, string $contents): void
{
    file_put_contents($path, $contents);
    chmod($path, 0755);
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
