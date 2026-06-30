<?php

declare(strict_types=1);

namespace Cpx\Runtime;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class ClassAliasAutoloader
{
    /** @var array<string, string> */
    protected array $classes = [];

    public function __construct(
        protected bool $shouldBeVerbose = false,
    ) {
        //
    }

    public function addAliases(string $autoloadRootDirectory): void
    {
        if (file_exists("{$autoloadRootDirectory}/vendor/composer/autoload_classmap.php")) {
            $classes = require "{$autoloadRootDirectory}/vendor/composer/autoload_classmap.php";

            foreach ($classes as $class => $path) {
                if (! str_contains($class, '\\')) {
                    continue;
                }

                $name = basename(str_replace('\\', '/', $class));

                if (! isset($this->classes[$name]) && class_exists($name)) {
                    $this->classes[$name] = $class;
                }
            }
        }

        if (! file_exists("{$autoloadRootDirectory}/vendor/composer/autoload_psr4.php")) {
            return;
        }

        $psr4 = require "{$autoloadRootDirectory}/vendor/composer/autoload_psr4.php";

        foreach ($psr4 as $namespace => $directories) {
            foreach ($directories as $directory) {
                if (! file_exists($directory)) {
                    continue;
                }

                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($directory),
                    RecursiveIteratorIterator::LEAVES_ONLY,
                );

                foreach ($iterator as $file) {
                    /** @var SplFileInfo $file */
                    if (! $file->isFile() || $file->getExtension() !== 'php') {
                        continue;
                    }

                    $classNamespace = $namespace;
                    $relativePath = str_replace($directory, '', $file->getPath());

                    if (! empty($relativePath)) {
                        $classNamespace .= strtr($relativePath, DIRECTORY_SEPARATOR, '\\').'\\';
                    }

                    $basename = $file->getBasename('.php');

                    if (str_ends_with($basename, 'Test')) {
                        continue;
                    }

                    $this->classes[$basename] = str_replace('\\\\', '\\', $classNamespace.$basename);
                }
            }
        }
    }

    public function aliasClass(string $class): void
    {
        if (str_contains($class, '\\')) {
            return;
        }

        $fullName = $this->classes[$class] ?? false;

        if (! $fullName || ! class_exists($fullName)) {
            return;
        }

        if ($this->shouldBeVerbose) {
            echo "Aliasing '{$class}' to '{$fullName}'".PHP_EOL;
        }

        class_alias($fullName, $class);
    }
}
