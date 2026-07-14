<?php

use Cpx\Process\ProcessRunner;

test('it returns the child exit code when using inherited stdio', function () {
    $directory = $this->temporaryDirectory('cpx-process');
    $binary = "{$directory}/exit-code";

    writeExecutable($binary, "#!/usr/bin/env php\n<?php exit(37);\n");

    expect((new ProcessRunner)->run([PHP_BINARY, $binary]))->toBe(37);
});

test('it forwards explicit environment variables to the child process', function () {
    $directory = $this->temporaryDirectory('cpx-process');
    $binary = "{$directory}/env-probe";
    $logFile = "{$directory}/env.txt";

    writeExecutable($binary, "#!/usr/bin/env php\n<?php file_put_contents('{$logFile}', getenv('CPX_TEST_ENV') === false ? 'unset' : getenv('CPX_TEST_ENV')); exit(0);\n");

    $status = (new ProcessRunner)->run([PHP_BINARY, $binary], ['CPX_TEST_ENV' => "line1\nline2 'quoted'"]);

    expect($status)->toBe(0)
        ->and(file_get_contents($logFile))->toBe("line1\nline2 'quoted'");
});

test('it runs the child process in the given working directory', function () {
    $directory = $this->temporaryDirectory('cpx-process');
    $binary = "{$directory}/cwd-probe";
    $logFile = "{$directory}/cwd.txt";

    writeExecutable($binary, "#!/usr/bin/env php\n<?php file_put_contents('{$logFile}', getcwd()); exit(0);\n");

    $status = (new ProcessRunner)->run([PHP_BINARY, $binary], [], $directory);

    expect($status)->toBe(0)
        ->and(realpath((string) file_get_contents($logFile)))->toBe(realpath($directory));
});

test('it delivers shell metacharacters as literal argv tokens', function () {
    $directory = $this->temporaryDirectory('cpx-process');
    $binary = "{$directory}/argv";
    $logFile = "{$directory}/argv.json";

    writeExecutable($binary, "#!/usr/bin/env php\n<?php file_put_contents('{$logFile}', json_encode(array_slice(\$argv, 1), JSON_THROW_ON_ERROR)); exit(0);\n");

    $status = (new ProcessRunner)->run([PHP_BINARY, $binary, 'two words', 'semi;colon', 'pipe|value', '$(touch injected)']);

    expect($status)->toBe(0)
        ->and(json_decode((string) file_get_contents($logFile), true))->toBe([
            'two words',
            'semi;colon',
            'pipe|value',
            '$(touch injected)',
        ])
        ->and(file_exists("{$directory}/injected"))->toBeFalse();
});

test('it forwards the supplied input to the child stdin', function () {
    $directory = $this->temporaryDirectory('cpx-process');
    $binary = "{$directory}/stdin-echo";
    $logFile = "{$directory}/stdin.txt";

    writeExecutable($binary, "#!/usr/bin/env php\n<?php file_put_contents('{$logFile}', stream_get_contents(STDIN)); exit(0);\n");

    ProcessRunner::fakeInput("hello\n");

    expect((new ProcessRunner)->run([PHP_BINARY, $binary]))->toBe(0)
        ->and(file_get_contents($logFile))->toBe("hello\n");
});

test('it propagates the exit code when input is supplied', function () {
    $directory = $this->temporaryDirectory('cpx-process');
    $binary = "{$directory}/stdin-exit";

    writeExecutable($binary, "#!/usr/bin/env php\n<?php stream_get_contents(STDIN); exit(9);\n");

    ProcessRunner::fakeInput("ignored\n");

    expect((new ProcessRunner)->run([PHP_BINARY, $binary]))->toBe(9);
});

test('it completes when the child never reads the supplied input', function () {
    $directory = $this->temporaryDirectory('cpx-process');
    $binary = "{$directory}/no-read";

    writeExecutable($binary, "#!/usr/bin/env php\n<?php exit(5);\n");

    ProcessRunner::fakeInput("pending data\n");

    expect((new ProcessRunner)->run([PHP_BINARY, $binary]))->toBe(5);
});

test('it executes batch scripts and propagates their exit code', function () {
    $directory = $this->temporaryDirectory('cpx-process');
    $binary = "{$directory}/tool.bat";

    file_put_contents($binary, "@exit /b 21\r\n");

    expect((new ProcessRunner)->run([$binary]))->toBe(21);
})->onlyOnWindows();

test('it reports a missing executable as a could-not-execute exit code', function () {
    $directory = $this->temporaryDirectory('cpx-process');

    expect((new ProcessRunner)->run(["{$directory}/missing"]))->toBe(ProcessRunner::COULD_NOT_EXECUTE);
});

test('it keeps the parent stdio streams usable across sequential runs', function () {
    $directory = $this->temporaryDirectory('cpx-process');
    $binary = "{$directory}/exit-code";

    writeExecutable($binary, "#!/usr/bin/env php\n<?php exit(11);\n");

    expect((new ProcessRunner)->run([PHP_BINARY, $binary]))->toBe(11)
        ->and((new ProcessRunner)->run([PHP_BINARY, $binary]))->toBe(11);
});
