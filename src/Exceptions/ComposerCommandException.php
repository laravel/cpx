<?php

declare(strict_types=1);

namespace Cpx\Exceptions;

use Cpx\Support\Str;
use Exception;

class ComposerCommandException extends Exception
{
    private const OUTPUT_LIMIT = 4000;

    /**
     * @param  list<string>  $arguments
     */
    public function __construct(array $arguments, string $output = '')
    {
        $message = 'Composer command failed: '.implode(' ', $arguments);
        $diagnostic = self::diagnostic($output);

        if ($diagnostic !== '') {
            $message .= "\n\n{$diagnostic}";
        }

        parent::__construct($message);
    }

    private static function diagnostic(string $output): string
    {
        $output = trim(Str::stripAnsi($output));

        if (strlen($output) <= self::OUTPUT_LIMIT) {
            return $output;
        }

        $half = intdiv(self::OUTPUT_LIMIT, 2);

        return substr($output, 0, $half)."\n... [output truncated] ...\n".substr($output, -$half);
    }
}
