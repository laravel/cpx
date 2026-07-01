<?php

use Cpx\Application;
use Cpx\Packages\Package;
use Laravel\Prompts\Prompt;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

pest()->extend(TestCase::class)->in('Feature', 'Unit');

function promptOutput(): string
{
    return Prompt::strippedContent();
}

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
    return "#!/usr/bin/env php\n<?php file_put_contents('{$logFile}', json_encode(array_slice(\$argv, 1), JSON_THROW_ON_ERROR)); exit({$exitCode});\n";
}

/**
 * A fake `composer` that writes a working autoloader into the install directory it is given.
 */
function composerAutoloaderStub(): string
{
    return "#!/usr/bin/env php\n<?php\n"
        ."\$dir = null;\n"
        ."foreach (\$argv as \$arg) { if (strncmp(\$arg, '--working-dir=', 14) === 0) { \$dir = substr(\$arg, 14); } }\n"
        ."if (\$dir !== null) { @mkdir(\$dir.'/vendor', 0755, true); file_put_contents(\$dir.'/vendor/autoload.php', '<?php'); }\n"
        .'exit(0);'."\n";
}

/**
 * @param  list<string>  $bins
 * @param  array<string, string>  $executables
 */
function prepareCachedPackage(string $package, array $bins, array $executables = [], ?string $version = null): string
{
    $target = $version === null ? $package : "{$package}:{$version}";
    $folder = Package::parse($target)->folder();
    [$vendor, $name] = explode('/', $package);
    $packageDirectory = cpx_path("{$folder}/vendor/{$vendor}/{$name}");

    mkdir($packageDirectory, 0755, true);
    file_put_contents($packageDirectory.'/composer.json', json_encode(['bin' => $bins], JSON_THROW_ON_ERROR));
    file_put_contents(cpx_path("{$folder}/vendor/autoload.php"), '<?php');

    foreach ($executables as $bin => $contents) {
        writeExecutable("{$packageDirectory}/{$bin}", $contents);
    }

    file_put_contents(cpx_path('.cpx_metadata.json'), json_encode([
        'packages' => [
            $target => [
                'last_updated' => date('Y-m-d H:i:s'),
                'last_run' => date('Y-m-d H:i:s'),
            ],
        ],
        'execCache' => [],
    ], JSON_THROW_ON_ERROR));

    return $packageDirectory;
}
