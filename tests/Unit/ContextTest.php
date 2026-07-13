<?php

use Cpx\Runtime\Context;

beforeEach(function () {
    foreach (['CPX_EXEC_FILE', 'CPX_EXEC_WORKING_DIRECTORY', 'CPX_EXEC_FIND_AUTOLOADER', 'CPX_EXEC_BOOT', 'CPX_EXEC_ALIAS', 'CPX_EXEC_VERBOSE'] as $variable) {
        $this->setEnvironmentVariable($variable, '');
    }
});

test('it builds from the environment with defaults enabled', function () {
    $directory = $this->temporaryDirectory('cpx-context');
    $this->useWorkingDirectory($directory);

    $context = Context::fromEnvironment();

    expect($context->workingDirectory)->toBe($directory)
        ->and($context->autoloadRoot)->toBeNull()
        ->and($context->shouldBoot)->toBeTrue()
        ->and($context->shouldAliasClasses)->toBeTrue()
        ->and($context->verbose)->toBeFalse();
});

test('it starts discovery from the executed file directory', function () {
    $directory = $this->temporaryDirectory('cpx-context');
    mkdir("{$directory}/vendor", 0755, true);
    mkdir("{$directory}/nested", 0755, true);
    file_put_contents("{$directory}/vendor/autoload.php", '<?php');
    file_put_contents("{$directory}/nested/script.php", '<?php');

    $this->setEnvironmentVariable('CPX_EXEC_FILE', "{$directory}/nested/script.php");

    $context = Context::fromEnvironment();

    expect($context->workingDirectory)->toBe("{$directory}/nested")
        ->and($context->autoloadRoot)->toBe($directory);
});

test('an explicit working directory overrides the executed file directory', function () {
    $directory = $this->temporaryDirectory('cpx-context');
    mkdir("{$directory}/vendor", 0755, true);
    file_put_contents("{$directory}/vendor/autoload.php", '<?php');
    file_put_contents("{$directory}/script.php", '<?php');

    $this->setEnvironmentVariable('CPX_EXEC_FILE', sys_get_temp_dir().'/cpx-gist-elsewhere.php');
    $this->setEnvironmentVariable('CPX_EXEC_WORKING_DIRECTORY', $directory);

    $context = Context::fromEnvironment();

    expect($context->workingDirectory)->toBe($directory)
        ->and($context->autoloadRoot)->toBe($directory);
});

test('it honors disabled toggles and verbosity', function () {
    $directory = $this->temporaryDirectory('cpx-context');
    mkdir("{$directory}/vendor", 0755, true);
    file_put_contents("{$directory}/vendor/autoload.php", '<?php');
    $this->useWorkingDirectory($directory);

    $this->setEnvironmentVariable('CPX_EXEC_FIND_AUTOLOADER', '0');
    $this->setEnvironmentVariable('CPX_EXEC_BOOT', '0');
    $this->setEnvironmentVariable('CPX_EXEC_ALIAS', '0');
    $this->setEnvironmentVariable('CPX_EXEC_VERBOSE', '1');

    $context = Context::fromEnvironment();

    expect($context->autoloadRoot)->toBeNull()
        ->and($context->shouldBoot)->toBeFalse()
        ->and($context->shouldAliasClasses)->toBeFalse()
        ->and($context->verbose)->toBeTrue();
});
