<?php

declare(strict_types=1);

namespace Cpx\Exceptions;

use Exception;

class ComposerCommandException extends Exception
{
    /**
     * @param  list<string>  $arguments
     */
    public function __construct(array $arguments)
    {
        parent::__construct('Composer command failed: '.implode(' ', $arguments));
    }
}
