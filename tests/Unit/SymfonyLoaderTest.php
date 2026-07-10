<?php

use Cpx\Runtime\Context;
use Cpx\Runtime\ReplLauncher;
use Cpx\Runtime\SymfonyLoader;

function symfonyKernelFixture(string $root, string $namespace = 'CpxSymfonyFixture'): void
{
    mkdir("{$root}/src", 0755, true);

    file_put_contents("{$root}/composer.json", json_encode([
        'require' => ['symfony/framework-bundle' => '^7.0'],
        'autoload' => ['psr-4' => ["{$namespace}\\" => 'src/']],
    ], JSON_THROW_ON_ERROR));

    file_put_contents("{$root}/src/Kernel.php", <<<PHP
    <?php

    namespace {$namespace};

    class Kernel
    {
        public function __construct(
            public string \$environment,
            public bool \$debug,
        ) {}

        public function boot(): void
        {
            \$GLOBALS['cpxSymfonyKernelBooted'] = true;
        }

        public function getContainer(): object
        {
            return new class
            {
                public string \$role = 'container';
            };
        }
    }
    PHP);

    require_once "{$root}/src/Kernel.php";
}

test('it does not support projects missing the bundles file', function () {
    $root = $this->temporaryDirectory('cpx-symfony');
    mkdir("{$root}/bin", 0755, true);
    file_put_contents("{$root}/bin/console", '<?php');

    expect((new SymfonyLoader)->supports(new Context($root, $root)))->toBeFalse();
});

test('it supports projects via bin/console and config/bundles.php', function () {
    $root = $this->temporaryDirectory('cpx-symfony');
    mkdir("{$root}/bin", 0755, true);
    mkdir("{$root}/config", 0755, true);
    file_put_contents("{$root}/bin/console", '<?php');
    file_put_contents("{$root}/config/bundles.php", '<?php return [];');

    expect((new SymfonyLoader)->supports(new Context($root, $root)))->toBeTrue();
});

test('it boots the kernel and exposes it with the container', function () {
    unset($GLOBALS['cpxSymfonyKernelBooted']);

    $root = $this->temporaryDirectory('cpx-symfony');
    symfonyKernelFixture($root, 'CpxSymfonyFixtureBoot');

    $variables = (new SymfonyLoader)->boot(new Context($root, $root));

    expect($variables)->toHaveKeys(['kernel', 'container'])
        ->and($variables['kernel']->environment)->toBe('dev')
        ->and($variables['kernel']->debug)->toBeTrue()
        ->and($variables['container']->role)->toBe('container')
        ->and($GLOBALS['cpxSymfonyKernelBooted'] ?? false)->toBeTrue();

    unset($GLOBALS['cpxSymfonyKernelBooted']);
});

test('it respects the configured application environment', function () {
    $this->setEnvironmentVariable('APP_ENV', 'prod');

    $root = $this->temporaryDirectory('cpx-symfony');
    symfonyKernelFixture($root, 'CpxSymfonyFixtureProd');

    $variables = (new SymfonyLoader)->boot(new Context($root, $root));

    expect($variables['kernel']->environment)->toBe('prod')
        ->and($variables['kernel']->debug)->toBeFalse();
});

test('it exposes no variables when no kernel class is discoverable', function () {
    $root = $this->temporaryDirectory('cpx-symfony');
    file_put_contents("{$root}/composer.json", json_encode([
        'require' => ['symfony/framework-bundle' => '^7.0'],
    ], JSON_THROW_ON_ERROR));

    expect((new SymfonyLoader)->boot(new Context($root, $root)))->toBe([]);
});

test('it does not provide a native repl', function () {
    expect(new SymfonyLoader)->not->toBeInstanceOf(ReplLauncher::class);
});
