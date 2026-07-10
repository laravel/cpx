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
        $file = ExecVariable::File->get();
        $workingDirectory = is_string($file) && $file !== '' ? dirname($file) : (getcwd() ?: '.');
        $shouldFindAutoloader = ExecVariable::FindAutoloader->get() !== '0';

        return new self(
            workingDirectory: $workingDirectory,
            autoloadRoot: $shouldFindAutoloader ? PhpExecutionHelper::findAutoloadRoot($workingDirectory) : null,
            shouldBoot: ExecVariable::Boot->get() !== '0',
            shouldAliasClasses: ExecVariable::AliasClasses->get() !== '0',
            verbose: ExecVariable::Verbose->get() === '1',
        );
    }
}
