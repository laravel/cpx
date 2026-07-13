<?php

declare(strict_types=1);

namespace Cpx\Runtime;

enum ExecVariable: string
{
    case File = 'CPX_EXEC_FILE';
    case Code = 'CPX_EXEC_CODE';
    case WorkingDirectory = 'CPX_EXEC_WORKING_DIRECTORY';
    case FindAutoloader = 'CPX_EXEC_FIND_AUTOLOADER';
    case Boot = 'CPX_EXEC_BOOT';
    case AliasClasses = 'CPX_EXEC_ALIAS';
    case Verbose = 'CPX_EXEC_VERBOSE';
    case Bin = 'CPX_EXEC_BIN';
    case PsyshAutoload = 'CPX_TINKER_PSYSH_AUTOLOAD';

    public function get(): string|false
    {
        return getenv($this->value);
    }
}
