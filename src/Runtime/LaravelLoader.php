<?php

declare(strict_types=1);

namespace Cpx\Runtime;

class LaravelLoader implements ProjectBooter
{
    public function supports(Context $context): bool
    {
        if ($context->autoloadRoot === null) {
            return false;
        }

        if (ComposerManifest::requires($context->autoloadRoot, 'laravel/framework')) {
            return true;
        }

        return is_file("{$context->autoloadRoot}/artisan")
            && is_file("{$context->autoloadRoot}/bootstrap/app.php");
    }

    /** @return array<string, object> */
    public function boot(Context $context): array
    {
        $bootstrap = "{$context->autoloadRoot}/bootstrap/app.php";

        if (! is_file($bootstrap)) {
            return [];
        }

        if (! defined('LARAVEL_START')) {
            define('LARAVEL_START', microtime(true));
        }

        $app = require $bootstrap;

        if (! is_object($app)) {
            return [];
        }

        if (method_exists($app, 'make')) {
            $kernel = $app->make('Illuminate\Contracts\Console\Kernel');

            if (is_object($kernel) && method_exists($kernel, 'bootstrap')) {
                $kernel->bootstrap();
            }
        }

        return ['app' => $app];
    }
}
