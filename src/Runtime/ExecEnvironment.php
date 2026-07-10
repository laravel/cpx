<?php

declare(strict_types=1);

namespace Cpx\Runtime;

readonly class ExecEnvironment
{
    public function __construct(
        public ?string $file = null,
        public ?string $code = null,
        public bool $findAutoloader = true,
        public bool $boot = true,
        public bool $aliasClasses = true,
        public bool $verbose = false,
    ) {}

    /** @return array{
        CPX_EXEC_FILE: string|false,
        CPX_EXEC_CODE: string|false,
        CPX_EXEC_FIND_AUTOLOADER: '0'|'1',
        CPX_EXEC_BOOT: '0'|'1',
        CPX_EXEC_ALIAS: '0'|'1',
        CPX_EXEC_VERBOSE: '0'|'1',
    } */
    public function toEnvironment(): array
    {
        return [
            'CPX_EXEC_FILE' => $this->file ?? false,
            'CPX_EXEC_CODE' => $this->code ?? false,
            'CPX_EXEC_FIND_AUTOLOADER' => $this->findAutoloader ? '1' : '0',
            'CPX_EXEC_BOOT' => $this->boot ? '1' : '0',
            'CPX_EXEC_ALIAS' => $this->aliasClasses ? '1' : '0',
            'CPX_EXEC_VERBOSE' => $this->verbose ? '1' : '0',
        ];
    }
}
