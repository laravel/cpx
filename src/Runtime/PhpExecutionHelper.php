<?php

declare(strict_types=1);

namespace Cpx\Runtime;

class PhpExecutionHelper
{
    public static ClassAliasAutoloader $classAliasAutoloader;

    /**
     * @return array<string, object> the variables to expose to user code
     */
    public static function prepare(Context $context): array
    {
        if ($context->autoloadRoot === null) {
            return [];
        }

        if ($context->verbose) {
            echo "Found autoload file at '{$context->autoloadRoot}/vendor/autoload.php'".PHP_EOL;
        }

        require_once "{$context->autoloadRoot}/vendor/autoload.php";

        $variables = $context->shouldBoot ? LoaderRegistry::resolve($context)->boot($context) : [];

        if ($context->shouldAliasClasses) {
            if ($context->verbose) {
                echo 'Aliasing classes'.PHP_EOL;
            }

            static::getClassAliasAutoloader($context->verbose)->addAliases($context->autoloadRoot);
            spl_autoload_register(static::getClassAliasAutoloader($context->verbose)->aliasClass(...));
        }

        return $variables;
    }

    public static function findAutoloadRoot(string $path): ?string
    {
        $root = $path;

        while (! file_exists("{$root}/vendor/autoload.php")) {
            $parent = realpath(dirname($root));

            // At a filesystem or drive root, dirname() returns its input unchanged.
            if ($parent === false || $parent === $root) {
                return null;
            }

            $root = $parent;
        }

        return $root;
    }

    public static function getClassAliasAutoloader(bool $shouldBeVerbose = false): ClassAliasAutoloader
    {
        return static::$classAliasAutoloader ??= new ClassAliasAutoloader($shouldBeVerbose);
    }
}
