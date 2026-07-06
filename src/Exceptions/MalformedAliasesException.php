<?php

declare(strict_types=1);

namespace Cpx\Exceptions;

use Exception;

class MalformedAliasesException extends Exception
{
    public function __construct(string $file)
    {
        parent::__construct("The aliases file at {$file} is malformed.");
    }
}
