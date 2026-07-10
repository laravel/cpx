<?php

declare(strict_types=1);

namespace Cpx\Runtime;

enum ExecVariable: string
{
    case File = 'CPX_EXEC_FILE';
    case Code = 'CPX_EXEC_CODE';
    case FindAutoloader = 'CPX_EXEC_FIND_AUTOLOADER';
    case Boot = 'CPX_EXEC_BOOT';
    case AliasClasses = 'CPX_EXEC_ALIAS';
    case Verbose = 'CPX_EXEC_VERBOSE';
    case PsyshAutoload = 'CPX_TINKER_PSYSH_AUTOLOAD';

    public function get(): string|false
    {
        return getenv($this->value);
    }
}
