<?php

use Cpx\Process\ProcessRunner;
use Cpx\Support\ChildScript;

function laravelTinkerProject(string $root, string $artisanLog, bool $withTinker = true): void
{
    mkdir("{$root}/vendor", 0755, true);
    mkdir("{$root}/bootstrap", 0755, true);

    file_put_contents("{$root}/vendor/autoload.php", '<?php');
    file_put_contents("{$root}/bootstrap/app.php", '<?php');
    file_put_contents("{$root}/artisan", argvLoggingBinary($artisanLog));

    if ($withTinker) {
        mkdir("{$root}/vendor/laravel/tinker", 0755, true);
    }
}

/**
 * @param  list<list<string>>  $commands
 * @param  list<array<string, string|false>>  $environments
 * @param  list<string|null>  $cwds
 */
function fakeProcessRunner(array &$commands, array &$environments, int $exitCode = 0, array &$cwds = []): void
{
    ProcessRunner::fake(function (array $command, array $env, ?string $cwd) use (&$commands, &$environments, &$cwds, $exitCode): int {
        $commands[] = $command;
        $environments[] = $env;
        $cwds[] = $cwd;

        return $exitCode;
    });
}

test('tinker proxies to artisan tinker in a laravel project', function () {
    $root = $this->temporaryDirectory('cpx-tinker');
    $log = "{$root}/artisan.json";
    laravelTinkerProject($root, $log);
    $this->useWorkingDirectory($root);

    [$status] = runCpxCommand(['tinker']);

    expect($status)->toBe(0)
        ->and(json_decode((string) file_get_contents($log), true))->toBe(['tinker']);
});

test('tinker forwards extra tokens to artisan tinker', function () {
    $root = $this->temporaryDirectory('cpx-tinker');
    $log = "{$root}/artisan.json";
    laravelTinkerProject($root, $log);
    $this->useWorkingDirectory($root);

    [$status] = runCpxCommand(['tinker', '--execute=2+2']);

    expect($status)->toBe(0)
        ->and(json_decode((string) file_get_contents($log), true))->toBe(['tinker', '--execute=2+2']);
});

test('tinker detects the project root from a nested subdirectory', function () {
    $root = $this->temporaryDirectory('cpx-tinker');
    $log = "{$root}/artisan.json";
    laravelTinkerProject($root, $log);
    mkdir("{$root}/app/Models", 0755, true);
    $this->useWorkingDirectory("{$root}/app/Models");

    [$status] = runCpxCommand(['tinker']);

    expect($status)->toBe(0)
        ->and(json_decode((string) file_get_contents($log), true))->toBe(['tinker']);
});

test('tinker ignores global options before the command name when forwarding', function () {
    $root = $this->temporaryDirectory('cpx-tinker');
    $log = "{$root}/artisan.json";
    laravelTinkerProject($root, $log);
    $this->useWorkingDirectory($root);

    [$status] = runCpxCommand(['-v', 'tinker', '--execute=2+2']);

    expect($status)->toBe(0)
        ->and(json_decode((string) file_get_contents($log), true))->toBe(['tinker', '--execute=2+2']);
});

test('tinker runs the artisan proxy from the project root', function () {
    $root = $this->temporaryDirectory('cpx-tinker');
    laravelTinkerProject($root, "{$root}/artisan.json");
    mkdir("{$root}/app/Models", 0755, true);
    $this->useWorkingDirectory("{$root}/app/Models");

    $commands = [];
    $environments = [];
    $cwds = [];
    fakeProcessRunner($commands, $environments, 0, $cwds);

    [$status] = runCpxCommand(['tinker']);

    expect($status)->toBe(0)
        ->and($cwds)->toBe([$root]);
});

test('tinker falls back to the bundled psysh outside laravel projects', function () {
    $this->useIsolatedComposerHome();
    prepareCachedPackage('psy/psysh', ['psysh']);

    $root = $this->temporaryDirectory('cpx-tinker');
    $this->useWorkingDirectory($root);

    $commands = [];
    $environments = [];
    fakeProcessRunner($commands, $environments);

    [$status] = runCpxCommand(['tinker']);

    expect($status)->toBe(0)
        ->and($commands)->toHaveCount(1)
        ->and($commands[0])->toBe([PHP_BINARY, ChildScript::path('tinker-runner.php')])
        ->and($environments[0]['CPX_TINKER_PSYSH_AUTOLOAD'])->toBe(cpx_path('psy/psysh/latest/vendor/autoload.php'))
        ->and($environments[0]['CPX_EXEC_BOOT'])->toBe('1');
});

test('tinker falls back to the bundled psysh when laravel/tinker is missing', function () {
    $this->useIsolatedComposerHome();
    prepareCachedPackage('psy/psysh', ['psysh']);

    $root = $this->temporaryDirectory('cpx-tinker');
    laravelTinkerProject($root, "{$root}/artisan.json", withTinker: false);
    $this->useWorkingDirectory($root);

    $commands = [];
    $environments = [];
    fakeProcessRunner($commands, $environments);

    [$status] = runCpxCommand(['tinker']);

    expect($status)->toBe(0)
        ->and($commands)->toHaveCount(1)
        ->and($commands[0][1])->toBe(ChildScript::path('tinker-runner.php'))
        ->and(file_exists("{$root}/artisan.json"))->toBeFalse();
});

test('tinker prefers the project psysh over the cached package', function () {
    $this->useIsolatedComposerHome();

    $root = $this->temporaryDirectory('cpx-tinker');
    mkdir("{$root}/vendor/psy/psysh", 0755, true);
    file_put_contents("{$root}/vendor/autoload.php", '<?php');
    $this->useWorkingDirectory($root);

    $calls = [];
    fakeComposer($calls);

    $commands = [];
    $environments = [];
    fakeProcessRunner($commands, $environments);

    [$status] = runCpxCommand(['tinker']);

    expect($status)->toBe(0)
        ->and($commands[0][1])->toBe(ChildScript::path('tinker-runner.php'))
        ->and($environments[0]['CPX_TINKER_PSYSH_AUTOLOAD'])->toBeFalse()
        ->and($calls)->toBe([]);
});

test('tinker forwards include files to the bundled psysh runner', function () {
    $this->useIsolatedComposerHome();
    prepareCachedPackage('psy/psysh', ['psysh']);

    $root = $this->temporaryDirectory('cpx-tinker');
    $this->useWorkingDirectory($root);

    $commands = [];
    $environments = [];
    fakeProcessRunner($commands, $environments);

    [$status] = runCpxCommand(['tinker', 'bootstrap-helpers.php']);

    expect($status)->toBe(0)
        ->and($commands[0])->toBe([PHP_BINARY, ChildScript::path('tinker-runner.php'), 'bootstrap-helpers.php']);
});
