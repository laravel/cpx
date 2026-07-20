<?php

declare(strict_types=1);

namespace Cpx\Support;

use Laravel\Prompts\Support\Logger;

class SilentLogger extends Logger
{
    public function __construct()
    {
        parent::__construct('cpx');
    }
}
