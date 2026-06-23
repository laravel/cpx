<?php

namespace Cpx;

use Cpx\Commands\Command;
use Cpx\Exceptions\ConsoleException;

class Console
{
    /**
     * @param  list<string>  $arguments
     * @param  array<string, string|null|list<string|null>>  $options
     * @param  list<string>  $flags
     */
    public function __construct(
        public string $rawInput,
        public string $command,
        public array $arguments = [],
        public array $options = [],
        public array $flags = [],
    ) {}

    /**
     * Parses $argv to get the command, arguments, options, and flags.
     *
     * @param  string|list<string>  $input  The $argv variable.
     * @param  array<string, string>  $shortOptions  Optional. An array with keys set to short options and their values set to the long option they're assigned to.
     * @param  list<string>  $flagOptions  Optional. An array of options to be treated as flags. If a flag is not defined here, it will be treated as an option.
     */
    public static function parse(string|array $input, array $shortOptions = [], array $flagOptions = []): Console
    {
        if (empty($input)) {
            return new Console('', '');
        }

        if (is_string($input)) {
            $input = trim($input);
            $parts = preg_split('/\s+(?=([^"]*"[^"]*")*[^"]*$)/', $input);
            $input = array_map(function (string $item): string {
                return trim($item, '"\'');
            }, $parts === false ? [] : $parts);
        }

        $command = array_shift($input) ?? '';
        $arguments = [];
        $options = [];
        $flags = [];
        $lastOption = null;

        foreach ($input as $arg) {
            $value = null;

            if (substr($arg, 0, 1) !== '-') {
                if ($lastOption) {
                    $value = $arg;
                    $arg = $lastOption;
                } else {
                    $arguments[] = $arg;
                    $lastOption = null;

                    continue;
                }
            } else {
                $argSplit = [];

                if (preg_match('/^--?([A-Z\d\-_]+)=?(.+)?$/i', $arg, $argSplit) !== 1) {
                    $arguments[] = $arg;
                    $lastOption = null;

                    continue;
                }

                $arg = $argSplit[1];

                if (isset($argSplit[2])) {
                    $value = $argSplit[2];
                }
            }

            if (array_key_exists($arg, $shortOptions)) {
                $arg = $shortOptions[$arg];
            }

            if (in_array($arg, $flagOptions, true)) {
                if (! in_array($arg, $flags, true)) {
                    $flags[] = $arg;
                }

                $lastOption = null;
            } else {
                if (array_key_exists($arg, $options)) {
                    if (is_array($options[$arg])) {
                        $options[$arg][] = $value;
                    } else {
                        if (is_null($options[$arg])) {
                            $options[$arg] = $value;
                        } elseif (! is_null($value)) {
                            $options[$arg] = [$options[$arg], $value];
                        }
                    }
                } else {
                    $options[$arg] = $value;
                }

                $lastOption = $value ? null : $arg;
            }
        }

        return new Console(implode(' ', $input), $command, $arguments, $options, $flags);
    }

    public function hasOption(string $option): bool
    {
        return array_key_exists($option, $this->options);
    }

    public function getOption(string $option): ?string
    {
        $value = $this->options[$option] ?? null;

        if (! is_array($value)) {
            return $value;
        }

        foreach ($value as $optionValue) {
            if ($optionValue !== null) {
                return $optionValue;
            }
        }

        return null;
    }

    public function hasFlag(string $flag): bool
    {
        return in_array($flag, $this->flags, true);
    }

    public function __toString(): string
    {
        return trim("{$this->command} {$this->getCommandInput()}");
    }

    public function getCommandInput(): string
    {
        return implode(' ', [$this->getArgumentsString(), $this->getOptionsString(), $this->getFlagsString()]);
    }

    public function getArgumentsString(): string
    {
        return implode(' ', $this->arguments);
    }

    public function getOptionsString(): string
    {
        $options = [];

        foreach ($this->options as $key => $value) {
            $values = is_array($value) ? $value : [$value];

            foreach ($values as $optionValue) {
                $options[] = $optionValue === null ? "--{$key}" : "--{$key}=".escapeshellarg($optionValue);
            }
        }

        return implode(' ', $options);
    }

    public function getFlagsString(): string
    {
        return implode(' ', $this->flags);
    }

    public function exec(bool $verbose = false): void
    {
        $descriptors = [
            0 => STDIN,
            1 => STDOUT,
            2 => STDERR,
        ];

        if ($verbose) {
            echo Command::BACKGROUND_CYAN."   Running command: '{$this}'   ".Command::COLOR_RESET;
        }

        $process = proc_open($this, $descriptors, $pipes);

        if (is_resource($process)) {
            proc_close($process);
        } else {
            throw new ConsoleException("Failed to run command '{$this->getCommandInput()}'");
        }
    }
}
