<?php

use Cpx\Runtime\Context;
use Cpx\Runtime\LaravelLoader;

test('it does not support projects missing the bootstrap file', function () {
    $root = $this->temporaryDirectory('cpx-laravel');
    file_put_contents("{$root}/artisan", '<?php');

    expect((new LaravelLoader)->supports(new Context($root, $root)))->toBeFalse();
});

test('it boots the console kernel and exposes the application', function () {
    $root = $this->temporaryDirectory('cpx-laravel');
    mkdir("{$root}/bootstrap", 0755, true);
    file_put_contents("{$root}/bootstrap/app.php", <<<'PHP'
    <?php

    return new class
    {
        public ?string $made = null;

        public object $kernel;

        public function __construct()
        {
            $this->kernel = new class
            {
                public bool $bootstrapped = false;

                public function bootstrap(): void
                {
                    $this->bootstrapped = true;
                }
            };
        }

        public function make(string $abstract): object
        {
            $this->made = $abstract;

            return $this->kernel;
        }
    };
    PHP);

    $variables = (new LaravelLoader)->boot(new Context($root, $root));

    expect($variables)->toHaveKey('app')
        ->and($variables['app']->made)->toBe('Illuminate\Contracts\Console\Kernel')
        ->and($variables['app']->kernel->bootstrapped)->toBeTrue()
        ->and(defined('LARAVEL_START'))->toBeTrue();
});

test('it exposes no variables when the bootstrap file is missing', function () {
    $root = $this->temporaryDirectory('cpx-laravel');

    expect((new LaravelLoader)->boot(new Context($root, $root)))->toBe([]);
});

test('it provides the artisan tinker command when laravel/tinker is installed', function () {
    $root = $this->temporaryDirectory('cpx-laravel');
    mkdir("{$root}/vendor/laravel/tinker", 0755, true);
    file_put_contents("{$root}/artisan", '<?php');

    expect((new LaravelLoader)->replCommand(new Context($root, $root)))
        ->toBe([PHP_BINARY, "{$root}/artisan", 'tinker']);
});

test('it provides no repl command when laravel/tinker is missing', function () {
    $root = $this->temporaryDirectory('cpx-laravel');
    file_put_contents("{$root}/artisan", '<?php');

    expect((new LaravelLoader)->replCommand(new Context($root, $root)))->toBeNull();
});

test('it provides no repl command when artisan is missing', function () {
    $root = $this->temporaryDirectory('cpx-laravel');
    mkdir("{$root}/vendor/laravel/tinker", 0755, true);

    expect((new LaravelLoader)->replCommand(new Context($root, $root)))->toBeNull();
});

test('it exposes no variables when the bootstrap file does not return an application', function () {
    $root = $this->temporaryDirectory('cpx-laravel');
    mkdir("{$root}/bootstrap", 0755, true);
    file_put_contents("{$root}/bootstrap/app.php", '<?php');

    expect((new LaravelLoader)->boot(new Context($root, $root)))->toBe([]);
});
