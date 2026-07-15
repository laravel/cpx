<?php

/**
 * Exec runs user code in a child PHP process, so these tests assert through
 * marker files instead of captured output (child stdio bypasses BufferedOutput).
 */
function laravelSpyProject(string $directory, ?string $bootMarker = null): void
{
    mkdir("{$directory}/vendor", 0755, true);
    mkdir("{$directory}/bootstrap", 0755, true);

    file_put_contents("{$directory}/vendor/autoload.php", '<?php');
    file_put_contents("{$directory}/artisan", '<?php');

    $recordBoot = $bootMarker === null
        ? ''
        : 'file_put_contents('.var_export($bootMarker, true).', \'booted\');';

    file_put_contents("{$directory}/bootstrap/app.php", <<<PHP
    <?php

    {$recordBoot}

    return new class
    {
        public function make(string \$abstract): object
        {
            return new class
            {
                public function bootstrap(): void {}
            };
        }
    };
    PHP);
}

test('exec runs inline code in a child process', function () {
    $directory = $this->temporaryDirectory('cpx-exec');
    $this->useWorkingDirectory($directory);
    $marker = "{$directory}/marker.txt";

    [$status] = runCpxCommand(['exec', '-r', 'file_put_contents('.var_export($marker, true).', "ran-inline");']);

    expect($status)->toBe(0)
        ->and(file_get_contents($marker))->toBe('ran-inline');
});

test('exec strips php open tags from inline code', function () {
    $directory = $this->temporaryDirectory('cpx-exec');
    $this->useWorkingDirectory($directory);
    $marker = "{$directory}/marker.txt";

    [$status] = runCpxCommand(['exec', '-r', '<?php file_put_contents('.var_export($marker, true).', "tagged"); ?>']);

    expect($status)->toBe(0)
        ->and(file_get_contents($marker))->toBe('tagged');
});

test('exec runs a php file in a child process', function () {
    $directory = $this->temporaryDirectory('cpx-exec');
    $this->useWorkingDirectory($directory);
    $marker = "{$directory}/marker.txt";

    file_put_contents("{$directory}/script.php", '<?php file_put_contents('.var_export($marker, true).', "ran-file");');

    [$status] = runCpxCommand(['exec', 'script.php']);

    expect($status)->toBe(0)
        ->and(file_get_contents($marker))->toBe('ran-file');
});

test('exec forwards the child exit code', function () {
    $directory = $this->temporaryDirectory('cpx-exec');
    $this->useWorkingDirectory($directory);

    [$status] = runCpxCommand(['exec', '-r', 'exit(3);']);

    expect($status)->toBe(3);
});

test('exec loads the nearest autoloader by default', function () {
    $directory = $this->temporaryDirectory('cpx-exec');
    $this->useWorkingDirectory($directory);
    $marker = "{$directory}/marker.txt";

    mkdir("{$directory}/vendor", 0755, true);
    file_put_contents("{$directory}/vendor/autoload.php", '<?php file_put_contents('.var_export($marker, true).', "autoloaded");');

    [$status] = runCpxCommand(['exec', '-r', 'clearstatcache();']);

    expect($status)->toBe(0)
        ->and(file_get_contents($marker))->toBe('autoloaded');
});

test('exec skips autoload discovery with --no-find-autoloader', function () {
    $directory = $this->temporaryDirectory('cpx-exec');
    $this->useWorkingDirectory($directory);
    $marker = "{$directory}/marker.txt";

    mkdir("{$directory}/vendor", 0755, true);
    file_put_contents("{$directory}/vendor/autoload.php", '<?php file_put_contents('.var_export($marker, true).', "autoloaded");');

    [$status] = runCpxCommand(['exec', '--no-find-autoloader', '-r', 'clearstatcache();']);

    expect($status)->toBe(0)
        ->and(file_exists($marker))->toBeFalse();
});

test('exec boots a detected laravel project by default', function () {
    $directory = $this->temporaryDirectory('cpx-exec');
    $this->useWorkingDirectory($directory);
    $marker = "{$directory}/boot-marker.txt";

    laravelSpyProject($directory, $marker);

    [$status] = runCpxCommand(['exec', '-r', 'clearstatcache();']);

    expect($status)->toBe(0)
        ->and(file_get_contents($marker))->toBe('booted');
});

test('exec skips the framework boot with --no-boot', function () {
    $directory = $this->temporaryDirectory('cpx-exec');
    $this->useWorkingDirectory($directory);
    $marker = "{$directory}/boot-marker.txt";

    laravelSpyProject($directory, $marker);

    [$status] = runCpxCommand(['exec', '--no-boot', '-r', 'clearstatcache();']);

    expect($status)->toBe(0)
        ->and(file_exists($marker))->toBeFalse();
});

test('exec exposes loader variables as real globals', function () {
    $directory = $this->temporaryDirectory('cpx-exec');
    $this->useWorkingDirectory($directory);
    $marker = "{$directory}/marker.txt";

    laravelSpyProject($directory);

    $probe = 'function cpxProbe() { global $app; file_put_contents('.var_export($marker, true).', is_object($app) ? "global-app" : "missing"); } cpxProbe();';

    [$status] = runCpxCommand(['exec', '-r', $probe]);

    expect($status)->toBe(0)
        ->and(file_get_contents($marker))->toBe('global-app');
});

test('exec rejects the removed --load-laravel-bootstrap flag', function () {
    $directory = $this->temporaryDirectory('cpx-exec');
    $this->useWorkingDirectory($directory);

    [$status, $output] = runCpxCommand(['exec', '--load-laravel-bootstrap', '-r', 'clearstatcache();']);

    expect($status)->not->toBe(0)
        ->and($output)->toContain('--load-laravel-bootstrap');
});

test('exec defines cpx_require in the child process', function () {
    $directory = $this->temporaryDirectory('cpx-exec');
    $this->useWorkingDirectory($directory);
    $marker = "{$directory}/marker.txt";

    [$status] = runCpxCommand(['exec', '-r', 'file_put_contents('.var_export($marker, true).', var_export(function_exists("cpx_require"), true));']);

    expect($status)->toBe(0)
        ->and(file_get_contents($marker))->toBe('true');
});

test('exec rejects a directory target', function () {
    $directory = $this->temporaryDirectory('cpx-exec');
    $this->useWorkingDirectory($directory);

    mkdir("{$directory}/subdir", 0755, true);

    [$status, $output] = runCpxCommand(['exec', 'subdir']);

    expect($status)->toBe(1)
        ->and($output)->toContain("Cannot execute 'subdir' because it is not a file.");
});

test('exec fails when the file does not exist', function () {
    $directory = $this->temporaryDirectory('cpx-exec');
    $this->useWorkingDirectory($directory);

    [$status, $output] = runCpxCommand(['exec', 'missing.php']);

    expect($status)->toBe(1)
        ->and($output)->toContain("File does not exist at 'missing.php'");
});

test('exec fails when no file or code is given', function () {
    [$status, $output] = runCpxCommand(['exec']);

    expect($status)->toBe(1)
        ->and($output)->toContain('Please supply the path to a file to execute.');
});

test('exec fails when inline code is empty', function () {
    [$status, $output] = runCpxCommand(['exec', '-r', '']);

    expect($status)->toBe(1)
        ->and($output)->toContain('Please supply code to execute with the -r option.');
});

test('exec reports an error when its stdout closes mid-stream', function () {
    $code = 'echo str_repeat("a", 1000), PHP_EOL; usleep(300000); echo str_repeat("b", 100000), PHP_EOL;';

    $process = proc_open(
        [PHP_BINARY, dirname(__DIR__, 2).'/cpx', 'exec', '-r', $code],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
    );

    assert(is_resource($process));

    fgets($pipes[1]);
    fclose($pipes[1]);
    fclose($pipes[0]);

    $stderr = (string) stream_get_contents($pipes[2]);
    fclose($pipes[2]);

    $status = proc_close($process);

    expect($status)->toBe(1)
        ->and($stderr)->toContain('Unable to write the child process output.');
})->skipOnWindows();
