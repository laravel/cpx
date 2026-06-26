<?php

use Cpx\Process\ProcessRunner;

test('it returns the child exit code when using inherited stdio', function () {
    $directory = $this->temporaryDirectory('cpx-process');
    $binary = "{$directory}/exit-code";

    writeExecutable($binary, "#!/usr/bin/env php\n<?php exit(37);\n");

    expect((new ProcessRunner)->run([$binary]))->toBe(37);
});

test('it captures stdout and stderr for commands that need output', function () {
    $directory = $this->temporaryDirectory('cpx-process');
    $binary = "{$directory}/capture";

    writeExecutable($binary, "#!/usr/bin/env php\n<?php fwrite(STDOUT, 'out'); fwrite(STDERR, 'err'); exit(0);\n");

    $result = (new ProcessRunner)->capture([$binary]);

    expect($result['exitCode'])->toBe(0)
        ->and($result['stdout'])->toBe('out')
        ->and($result['stderr'])->toBe('err');
});

test('it delivers shell metacharacters as literal argv tokens', function () {
    $directory = $this->temporaryDirectory('cpx-process');
    $binary = "{$directory}/argv";
    $logFile = "{$directory}/argv.json";

    writeExecutable($binary, "#!/usr/bin/env php\n<?php file_put_contents('{$logFile}', json_encode(array_slice(\$argv, 1), JSON_THROW_ON_ERROR)); exit(0);\n");

    $status = (new ProcessRunner)->run([$binary, 'two words', 'semi;colon', 'pipe|value', '$(touch injected)']);

    expect($status)->toBe(0)
        ->and(json_decode((string) file_get_contents($logFile), true))->toBe([
            'two words',
            'semi;colon',
            'pipe|value',
            '$(touch injected)',
        ])
        ->and(file_exists("{$directory}/injected"))->toBeFalse();
});

test('it reports a missing executable as a could-not-execute exit code', function () {
    $directory = $this->temporaryDirectory('cpx-process');

    expect((new ProcessRunner)->run(["{$directory}/missing"]))->toBe(ProcessRunner::COULD_NOT_EXECUTE);
});
