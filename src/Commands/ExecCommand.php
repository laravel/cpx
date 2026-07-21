<?php

declare(strict_types=1);

namespace Cpx\Commands;

use Closure;
use Cpx\Exceptions\GistException;
use Cpx\Gists\GistClient;
use Cpx\Gists\GistFile;
use Cpx\Gists\GistUrl;
use Cpx\Process\ProcessRunner;
use Cpx\Runtime\ExecEnvironment;
use Cpx\Support\ChildScript;
use Cpx\Support\Filesystem;
use Cpx\Support\Interactivity;
use Cpx\Support\Result;
use Laravel\Prompts\Exceptions\NonInteractiveValidationException;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\select;

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
        if ($input->getOption('run') !== null) {
            $run = $input->getOption('run');

            if (! is_string($run) || $run === '') {
                return Result::failure($output, 'Please supply code to execute with the -r option.');
            }

            return $this->runScript($input, $output, code: $this->normalizeCode($run));
        }

        $target = $input->getArgument('file');

        if (! is_string($target) || $target === '') {
            return Result::failure($output, 'Please supply the path to a file to execute.');
        }

        $gist = GistUrl::tryFrom($target);

        if ($gist !== null) {
            try {
                $file = $this->downloadGist($gist, $input);
            } catch (GistException $exception) {
                return $this->gistFailure($exception, $output);
            }

            try {
                return $this->runScript($input, $output, file: $file, workingDirectory: getcwd() ?: null);
            } finally {
                @unlink($file);
            }
        }

        if (GistUrl::isUrl($target)) {
            return $this->gistFailure(GistException::unsupportedUrl($target), $output);
        }

        $file = realpath($target);

        if ($file === false) {
            return Result::failure($output, "File does not exist at '{$target}'");
        }

        if (! is_file($file)) {
            return Result::failure($output, "Cannot execute '{$target}' because it is not a file.");
        }

        return $this->runScript($input, $output, file: $file);
    }

    private function gistFailure(GistException $exception, OutputInterface $output): int
    {
        if (! Interactivity::isInteractive()) {
            return Result::failure($output, $exception->getMessage());
        }

        $exception->render();

        return self::FAILURE;
    }

    private function runScript(
        InputInterface $input,
        OutputInterface $output,
        ?string $file = null,
        ?string $code = null,
        ?string $workingDirectory = null,
    ): int {
        $environment = new ExecEnvironment(
            file: $file,
            code: $code,
            workingDirectory: $workingDirectory,
            findAutoloader: $input->getOption('find-autoloader') === true,
            boot: $input->getOption('boot') === true,
            aliasClasses: $input->getOption('alias-classes') === true,
            verbose: $output->isVerbose(),
        );

        return (new ProcessRunner)->run(
            [PHP_BINARY, ChildScript::path('exec-bootstrap.php')],
            $environment->toEnvironment(),
        );
    }

    /** @throws GistException When the gist cannot be downloaded or is not a runnable PHP script. */
    private function downloadGist(GistUrl $gist, InputInterface $input): string
    {
        $script = (new GistClient)->fetchFile($gist, $this->chooseGistFile($input));
        $file = Filesystem::joinPath(sys_get_temp_dir(), 'cpx-gist-'.bin2hex(random_bytes(8)).'.php');

        if (@file_put_contents($file, $script->content) === false) {
            throw GistException::unwritableTemporaryFile($file);
        }

        return $file;
    }

    /** @return (Closure(list<GistFile>): GistFile)|null */
    private function chooseGistFile(InputInterface $input): ?Closure
    {
        if (! $input->isInteractive()) {
            return null;
        }

        return function (array $files): GistFile {
            $files = array_values($files);

            try {
                $chosen = select(
                    label: 'Which file of the gist would you like to run?',
                    options: array_map(fn (GistFile $file): string => $file->filename, $files),
                );
            } catch (NonInteractiveValidationException) {
                throw GistException::ambiguousPhpFiles($files);
            }

            foreach ($files as $file) {
                if ($file->filename === $chosen) {
                    return $file;
                }
            }

            throw new RuntimeException("Unknown gist file '{$chosen}'.");
        };
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
