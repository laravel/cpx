<?php

declare(strict_types=1);

namespace Cpx\Commands;

use Cpx\Exceptions\GistException;
use Cpx\Gists\GistClient;
use Cpx\Gists\GistUrl;
use Cpx\Process\ProcessRunner;
use Cpx\Runtime\ExecEnvironment;
use Cpx\Support\ChildScript;
use Cpx\Support\Filesystem;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\error;

#[AsCommand(
    name: 'exec',
    description: 'Invoke a PHP file, inline PHP code, or a GitHub gist',
)]
class ExecCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('file', InputArgument::OPTIONAL, 'PHP file or GitHub gist URL to invoke');
        $this->addOption('run', 'r', InputOption::VALUE_REQUIRED, 'Run PHP code without <?php ?> tags');
        $this->addOption('find-autoloader', null, InputOption::VALUE_NEGATABLE, 'Find and load the nearest Composer autoloader', true);
        $this->addOption('boot', null, InputOption::VALUE_NEGATABLE, 'Boot the detected framework when available', true);
        $this->addOption('alias-classes', null, InputOption::VALUE_NEGATABLE, 'Alias classes from loaded autoloaders', true);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $code = null;
        $file = null;
        $temporaryGistFile = null;

        if ($input->getOption('run') !== null) {
            $run = $input->getOption('run');

            if (! is_string($run) || $run === '') {
                error('Please supply code to execute with the -r option.');

                return self::FAILURE;
            }

            $code = $this->normalizeCode($run);
        } else {
            $target = $input->getArgument('file');

            if (! is_string($target) || $target === '') {
                error('Please supply the path to a file to execute.');

                return self::FAILURE;
            }

            $gist = GistUrl::tryFrom($target);

            if ($gist !== null) {
                try {
                    $file = $temporaryGistFile = $this->downloadGist($gist);
                } catch (GistException $exception) {
                    $exception->render();

                    return self::FAILURE;
                }
            } else {
                $file = realpath($target);

                if ($file === false) {
                    error("File does not exist at '{$target}'");

                    return self::FAILURE;
                }

                if (! is_file($file)) {
                    error("Cannot execute '{$target}' because it is not a file.");

                    return self::FAILURE;
                }
            }
        }

        $environment = new ExecEnvironment(
            file: $file,
            code: $code,
            workingDirectory: $temporaryGistFile === null ? null : (getcwd() ?: null),
            findAutoloader: $input->getOption('find-autoloader') === true,
            boot: $input->getOption('boot') === true,
            aliasClasses: $input->getOption('alias-classes') === true,
            verbose: $output->isVerbose(),
        );

        try {
            return (new ProcessRunner)->run(
                [PHP_BINARY, ChildScript::path('exec-bootstrap.php')],
                $environment->toEnvironment(),
            );
        } finally {
            if ($temporaryGistFile !== null) {
                @unlink($temporaryGistFile);
            }
        }
    }

    /** @throws GistException When the gist cannot be downloaded or is not a runnable PHP script. */
    private function downloadGist(GistUrl $gist): string
    {
        $script = (new GistClient)->fetchFile($gist);
        $file = Filesystem::joinPath(sys_get_temp_dir(), 'cpx-gist-'.bin2hex(random_bytes(8)).'.php');

        file_put_contents($file, $script->content);

        return $file;
    }

    private function normalizeCode(string $code): string
    {
        if (str_starts_with($code, '<?php')) {
            $code = substr($code, 5);

            if (str_ends_with(trim($code), '?>')) {
                $code = substr(rtrim($code), 0, -2);
            }
        }

        if (! str_ends_with(trim($code), ';')) {
            $code .= ';';
        }

        return $code;
    }
}
