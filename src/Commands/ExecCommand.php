<?php

declare(strict_types=1);

namespace Cpx\Commands;

use Cpx\PhpExecutionHelper;

class ExecCommand extends Command
{
    public string $path;

    public function __invoke(): void
    {
        if ($this->console->hasOption('r')) {
            $code = $this->console->getOption('r');

            if (empty($code)) {
                $this->error('Please supply code to execute with the -r option.');

                return;
            }

            if (str_starts_with($code, '<?php')) {
                $code = substr($code, 5);

                if (str_ends_with(trim($code), '?>')) {
                    $code = substr($code, 0, -2);
                }
            }

            if (! str_ends_with(trim($code), ';')) {
                $code .= ';';
            }

            $directory = getcwd();

            if ($directory === false) {
                $this->error('Unable to determine the current working directory.');

                return;
            }

            $this->autoload($directory);

            eval($code);
            echo PHP_EOL;

            return;
        }

        if (empty($this->console->arguments[0])) {
            $this->error('Please supply the path to a file to execute.');

            return;
        }

        $path = realpath($this->console->arguments[0]);

        if ($path === false || ! file_exists($path)) {
            $this->error("File does not exist at '{$this->console->arguments[0]}'");

            return;
        }

        $this->path = $path;

        $this->autoload(dirname($this->path));

        $this->runFile();
    }

    protected function autoload(string $directory): void
    {
        $shouldFindAutoloader = $this->booleanOption('find-autoloader', true);
        $shouldLoadLaravelBootstrap = $this->booleanOption('load-laravel-bootstrap', true);
        $shouldAliasClasses = $this->booleanOption('alias-classes', true);
        $shouldBeVerbose = $this->booleanOption('verbose', false);

        PhpExecutionHelper::init($directory, $shouldFindAutoloader, $shouldLoadLaravelBootstrap, $shouldAliasClasses, $shouldBeVerbose);
    }

    protected function booleanOption(string $option, bool $default): bool
    {
        $value = $this->console->getOption($option);

        if ($value === null) {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default;
    }

    public function runFile(): void
    {
        require $this->path;
    }
}
