<?php

namespace Cpx\Commands;

use Cpx\PhpExecutionHelper;

class ExecCommand extends Command
{
    public string $path;

    public function __invoke()
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

            $this->autoload(getcwd());

            eval($code);
            echo PHP_EOL;

            return;
        }

        if (empty($this->console->arguments[0])) {
            $this->error('Please supply the path to a file to execute.');

            return;
        }

        $this->path = realpath($this->console->arguments[0]);

        if (! file_exists($this->path)) {
            $this->error("File does not exist at '{$this->path}'");

            return;
        }

        $this->autoload(dirname($this->path));

        $this->runFile();
    }

    protected function autoload(string $directory): void
    {
        $shouldFindAutoloader = $this->console->getOption('find-autoloader') ?? true;
        $shouldLoadLaravelBootstrap = $this->console->getOption('load-laravel-bootstrap') ?? true;
        $shouldAliasClasses = $this->console->getOption('alias-classes') ?? true;
        $shouldBeVerbose = $this->console->getOption('verbose') ?? false;

        PhpExecutionHelper::init($directory, $shouldFindAutoloader, $shouldLoadLaravelBootstrap, $shouldAliasClasses, $shouldBeVerbose);
    }

    public function runFile(): void
    {
        require $this->path;
    }
}
