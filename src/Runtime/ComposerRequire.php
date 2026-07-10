<?php

declare(strict_types=1);

namespace Cpx\Runtime;

use RuntimeException;

/** Child-process bridge for composer_require(): a spawned cpx process installs the sandbox. */
class ComposerRequire
{
    public const COMMAND = '__cpx_sandbox';

    public static function load(string ...$packages): void
    {
        if ($packages === []) {
            return;
        }

        $sandboxPath = self::ensureSandbox(array_values($packages));
        $autoloadFile = "{$sandboxPath}/vendor/autoload.php";

        if (! is_file($autoloadFile)) {
            throw new RuntimeException("Autoload file not found in {$sandboxPath}/vendor/. Composer installation may have failed.");
        }

        if (isset(PhpExecutionHelper::$classAliasAutoloader)) {
            PhpExecutionHelper::$classAliasAutoloader->addAliases($sandboxPath);
        }

        require_once $autoloadFile;
    }

    /**
     * @param  list<string>  $packages
     */
    private static function ensureSandbox(array $packages): string
    {
        $cpx = ExecVariable::Bin->get();

        if (! is_string($cpx) || $cpx === '') {
            throw new RuntimeException('composer_require() needs the '.ExecVariable::Bin->value.' environment variable pointing at the cpx binary.');
        }

        $process = proc_open(
            [PHP_BINARY, $cpx, self::COMMAND, ...$packages],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => STDERR],
            $pipes,
        );

        if ($process === false) {
            throw new RuntimeException('Unable to start the cpx sandbox process.');
        }

        fclose($pipes[0]);
        $output = (string) stream_get_contents($pipes[1]);
        fclose($pipes[1]);

        if (proc_close($process) !== 0) {
            throw new RuntimeException('Failed to install: '.implode(' ', $packages));
        }

        $lines = array_values(array_filter(array_map(trim(...), explode("\n", $output))));
        $sandboxPath = array_pop($lines);

        if ($sandboxPath === null) {
            throw new RuntimeException('The cpx sandbox process did not report a sandbox path.');
        }

        // The sandbox path is the last line; anything else stays visible.
        foreach ($lines as $line) {
            echo $line.PHP_EOL;
        }

        return $sandboxPath;
    }
}
