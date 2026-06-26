<?php

use Cpx\Composer\ComposerRunner;
use Cpx\Packages\Package;

test('it passes composer arguments as argv tokens', function () {
    $binDirectory = $this->temporaryDirectory('cpx-composer-bin');
    $workingDirectory = $this->temporaryDirectory('cpx-composer working;dir');
    $logFile = $this->temporaryDirectory('cpx-composer-log').'/argv.json';

    writeExecutable($binDirectory.'/composer', "#!/usr/bin/env php\n<?php file_put_contents('{$logFile}', json_encode(array_slice(\$argv, 1), JSON_THROW_ON_ERROR)); echo 'installed'.PHP_EOL; exit(0);\n");
    $this->setEnvironmentVariable('PATH', $binDirectory.PATH_SEPARATOR.getenv('PATH'));

    $output = ComposerRunner::run(['require', 'vendor/package:^1@dev', '--no-progress'], $workingDirectory);

    expect($output)->toBe(['installed'])
        ->and(json_decode((string) file_get_contents($logFile), true))->toBe([
            'require',
            'vendor/package:^1@dev',
            '--no-progress',
            '--no-interaction',
            '--quiet',
            "--working-dir={$workingDirectory}",
        ]);
});

test('it includes stderr when composer exits non-zero', function () {
    $binDirectory = $this->temporaryDirectory('cpx-composer-bin');

    writeExecutable($binDirectory.'/composer', "#!/usr/bin/env php\n<?php fwrite(STDERR, 'composer failed'); exit(12);\n");
    $this->setEnvironmentVariable('PATH', $binDirectory.PATH_SEPARATOR.getenv('PATH'));

    ComposerRunner::run(['update']);
})->throws(Exception::class, 'composer failed');

test('package installation calls composer with argv arrays', function () {
    $this->useIsolatedComposerHome();

    $binDirectory = $this->temporaryDirectory('cpx-composer-bin');
    $logFile = $this->temporaryDirectory('cpx-composer-log').'/argv.json';

    writeExecutable($binDirectory.'/composer', "#!/usr/bin/env php\n<?php file_put_contents('{$logFile}', json_encode(array_slice(\$argv, 1), JSON_THROW_ON_ERROR)); exit(0);\n");
    $this->setEnvironmentVariable('PATH', $binDirectory.PATH_SEPARATOR.getenv('PATH'));

    Package::parse('vendor/package:^1@dev')->installOrUpdatePackage(updateCheck: false);

    expect(json_decode((string) file_get_contents($logFile), true))->toContain('vendor/package:^1@dev')
        ->and(json_decode((string) file_get_contents($logFile), true))->not->toContain('vendor/package:^1@dev --no-interaction');
});
