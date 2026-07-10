<?php

declare(strict_types=1);

namespace Cpx\Runtime;

class SymfonyLoader implements ProjectBooter
{
    public function supports(Context $context): bool
    {
        if ($context->autoloadRoot === null) {
            return false;
        }

        if (ComposerManifest::requires($context->autoloadRoot, 'symfony/framework-bundle')) {
            return true;
        }

        return is_file("{$context->autoloadRoot}/bin/console")
            && is_file("{$context->autoloadRoot}/config/bundles.php");
    }

    /** @return array<string, object> */
    public function boot(Context $context): array
    {
        $root = (string) $context->autoloadRoot;

        $this->loadDotenv($root);

        $kernelClass = $this->kernelClass($root);

        if ($kernelClass === null) {
            return [];
        }

        $environment = $this->environment();

        $kernel = new $kernelClass($environment, $this->debug($environment));

        if (method_exists($kernel, 'boot')) {
            $kernel->boot();
        }

        $variables = ['kernel' => $kernel];

        if (method_exists($kernel, 'getContainer')) {
            $container = $kernel->getContainer();

            if (is_object($container)) {
                $variables['container'] = $container;
            }
        }

        return $variables;
    }

    private function loadDotenv(string $root): void
    {
        $dotenvClass = 'Symfony\Component\Dotenv\Dotenv';

        if (! class_exists($dotenvClass) || ! is_file("{$root}/.env")) {
            return;
        }

        $dotenv = new $dotenvClass;

        if (is_object($dotenv) && method_exists($dotenv, 'bootEnv')) {
            $dotenv->bootEnv("{$root}/.env");
        }
    }

    private function kernelClass(string $root): ?string
    {
        if (class_exists('App\Kernel')) {
            return 'App\Kernel';
        }

        $decoded = json_decode((string) @file_get_contents("{$root}/composer.json"), true);

        if (! is_array($decoded)) {
            return null;
        }

        $psr4 = $decoded['autoload']['psr-4'] ?? [];

        if (! is_array($psr4)) {
            return null;
        }

        foreach ($psr4 as $namespace => $path) {
            if (! is_string($namespace) || $path !== 'src/') {
                continue;
            }

            $kernelClass = "{$namespace}Kernel";

            if (class_exists($kernelClass)) {
                return $kernelClass;
            }
        }

        return null;
    }

    private function environment(): string
    {
        $environment = $_SERVER['APP_ENV'] ?? $_ENV['APP_ENV'] ?? 'dev';

        return is_string($environment) && $environment !== '' ? $environment : 'dev';
    }

    private function debug(string $environment): bool
    {
        $debug = $_SERVER['APP_DEBUG'] ?? $_ENV['APP_DEBUG'] ?? null;

        if ($debug === null) {
            return $environment !== 'prod';
        }

        return filter_var($debug, FILTER_VALIDATE_BOOL);
    }
}
