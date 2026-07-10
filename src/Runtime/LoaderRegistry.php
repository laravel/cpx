<?php

declare(strict_types=1);

namespace Cpx\Runtime;

class LoaderRegistry
{
    /** @return list<ProjectBooter> */
    public static function loaders(): array
    {
        return [
            new GenericLoader,
        ];
    }

    public static function resolve(Context $context): ProjectBooter
    {
        foreach (self::loaders() as $loader) {
            if ($loader->supports($context)) {
                return $loader;
            }
        }

        return new GenericLoader;
    }
}
