<?php

declare(strict_types=1);

namespace Cpx\Commands;

class HelpCommand extends Command
{
    public function __invoke(bool $unknownCommand = false): void
    {
        if ($unknownCommand) {
            $this->error("Unrecognised command {$this->console->command}");
        }

        $this->success('cpx - A Composer package runner with on-demand execution and package management.');
        $this->line('Usage:');
        $this->line('  '.Command::COLOR_GREEN.'cpx <vendor/package[:version]> [args]   '.Command::COLOR_RESET.'Run a Composer package\'s bin command');
        $this->line('  '.Command::COLOR_GREEN.'cpx check                               '.Command::COLOR_RESET.'Run a static analysis tool over a project');
        $this->line('  '.Command::COLOR_GREEN.'cpx test                                '.Command::COLOR_RESET.'Run a testing framework over a project');
        $this->line('  '.Command::COLOR_GREEN.'cpx format                              '.Command::COLOR_RESET.'Run a code formatter over a project');
        $this->line('  '.Command::COLOR_GREEN.'cpx update                              '.Command::COLOR_RESET.'Update all packages');
        $this->line('  '.Command::COLOR_GREEN.'cpx update <vendor/package>             '.Command::COLOR_RESET.'Update all versions of a package');
        $this->line('  '.Command::COLOR_GREEN.'cpx clean                               '.Command::COLOR_RESET.'Clean unused packages (older than 30 days)');
        $this->line('  '.Command::COLOR_GREEN.'cpx clean --all                         '.Command::COLOR_RESET.'Clean all packages');
        $this->line('  '.Command::COLOR_GREEN.'cpx exec </path/to/php/file.php>        '.Command::COLOR_RESET.'Invoke a PHP file');
        $this->line('  '.Command::COLOR_GREEN.'cpx exec -r <code>                      '.Command::COLOR_RESET.'Run PHP code without <?php ?> tags');
        $this->line('  '.Command::COLOR_GREEN.'cpx tinker                              '.Command::COLOR_RESET.'Open an interactive REPL');
        $this->line('  '.Command::COLOR_GREEN.'cpx list                                '.Command::COLOR_RESET.'List all installed packages');
        $this->line('  '.Command::COLOR_GREEN.'cpx aliases                             '.Command::COLOR_RESET.'Show aliased package names to run via `cpx <alias>`');
        $this->line('  '.Command::COLOR_GREEN.'cpx help                                '.Command::COLOR_RESET.'Show this help message');
    }
}
