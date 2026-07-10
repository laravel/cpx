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

    /** @return array<string, string|false> */
    public function toEnvironment(): array
    {
        return [
            ExecVariable::File->value => $this->file ?? false,
            ExecVariable::Code->value => $this->code ?? false,
            ExecVariable::FindAutoloader->value => $this->findAutoloader ? '1' : '0',
            ExecVariable::Boot->value => $this->boot ? '1' : '0',
            ExecVariable::AliasClasses->value => $this->aliasClasses ? '1' : '0',
            ExecVariable::Verbose->value => $this->verbose ? '1' : '0',
            ExecVariable::Bin->value => self::binPath(),
        ];
    }

    private static function binPath(): string
    {
        return Environment::isPhar()
            ? Environment::pharPath()
            : dirname(__DIR__, 2).'/cpx';
    }
}
