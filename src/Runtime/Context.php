<?php

declare(strict_types=1);

namespace Cpx\Runtime;

readonly class Context
{
    public function __construct(
        public string $workingDirectory,
        public ?string $autoloadRoot,
        public bool $shouldBoot = true,
        public bool $shouldAliasClasses = true,
        public bool $verbose = false,
    ) {}

    public static function fromEnvironment(): self
    {
        $file = getenv('CPX_EXEC_FILE');
        $workingDirectory = is_string($file) && $file !== '' ? dirname($file) : (getcwd() ?: '.');
        $shouldFindAutoloader = getenv('CPX_EXEC_FIND_AUTOLOADER') !== '0';

        return new self(
            workingDirectory: $workingDirectory,
            autoloadRoot: $shouldFindAutoloader ? PhpExecutionHelper::findAutoloadRoot($workingDirectory) : null,
            shouldBoot: getenv('CPX_EXEC_BOOT') !== '0',
            shouldAliasClasses: getenv('CPX_EXEC_ALIAS') !== '0',
            verbose: getenv('CPX_EXEC_VERBOSE') === '1',
        );
    }
}
