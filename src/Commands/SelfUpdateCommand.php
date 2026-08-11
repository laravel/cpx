<?php

declare(strict_types=1);

namespace Cpx\Commands;

use Cpx\Commands\Concerns\OutputsJson;
use Cpx\Exceptions\SelfUpdateException;
use Cpx\Process\ProcessRunner;
use Cpx\Runtime\Environment;
use Cpx\SelfUpdate\PharReplacer;
use Cpx\SelfUpdate\Release;
use Cpx\SelfUpdate\ReleaseClient;
use Cpx\Support\Filesystem;
use Cpx\Support\Interactivity;
use Cpx\Support\JsonEnvelope;
use Cpx\Support\Result;
use Cpx\Support\SilentLogger;
use Cpx\Version;
use Laravel\Prompts\Output\BufferedConsoleOutput;
use Laravel\Prompts\Prompt;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\callout;
use function Laravel\Prompts\info;

#[AsCommand(
    name: 'self-update',
    description: 'Update cpx to the latest version',
)]
class SelfUpdateCommand extends Command
{
    use OutputsJson;

    public function __construct(
        private readonly ReleaseClient $releases = new ReleaseClient,
        private readonly PharReplacer $replacer = new PharReplacer,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addJsonOption();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $json = $this->wantsJson($input);
        $current = Version::resolve();

        try {
            if (! Environment::isPhar()) {
                throw SelfUpdateException::notRunningFromPhar();
            }

            $target = Environment::pharPath();
            $release = $this->releases->latest();

            if ($release->tag === $current) {
                return $this->reportAlreadyLatest($output, $current, $json);
            }

            if (! $json) {
                info("Downloading cpx {$release->tag}...");
            }

            $this->warmReportingClasses($output, $current, $release, $target, $json);

            ProcessRunner::withLogger(new SilentLogger, fn () => $this->replacer->replace(
                $target,
                $release,
                fn (string $destination) => $this->releases->download($release, $destination),
            ));

            return $this->reportUpdated($output, $current, $release, $target, $json);
        } catch (SelfUpdateException $exception) {
            if (! Interactivity::isInteractive()) {
                return Result::failure($output, $exception->getMessage());
            }

            $exception->render();

            return self::FAILURE;
        }
    }

    /** The running phar cannot autoload after its file is swapped, so preload everything the report touches. */
    private function warmReportingClasses(OutputInterface $output, string $current, Release $release, string $target, bool $json): void
    {
        class_exists(SelfUpdateException::class);

        if ($json) {
            class_exists(Result::class);
            class_exists(JsonEnvelope::class);

            return;
        }

        Prompt::setOutput(new BufferedConsoleOutput);

        try {
            $this->renderUpdatedCallout($current, $release, $target);
        } finally {
            Prompt::setOutput($output);
        }
    }

    private function reportAlreadyLatest(OutputInterface $output, string $current, bool $json): int
    {
        if ($json) {
            return Result::success($output, ['updated' => false, 'version' => $current]);
        }

        info("cpx {$current} is already the latest version.");

        return self::SUCCESS;
    }

    private function reportUpdated(OutputInterface $output, string $current, Release $release, string $target, bool $json): int
    {
        if ($json) {
            return Result::success($output, ['updated' => true, 'from' => $current, 'to' => $release->tag, 'path' => $target]);
        }

        $this->renderUpdatedCallout($current, $release, $target);

        return self::SUCCESS;
    }

    private function renderUpdatedCallout(string $current, Release $release, string $target): void
    {
        $content = ["Updated cpx from {$current} to {$release->tag}."];

        if (str_contains(Filesystem::normalizePath($target), '/vendor/cpx/cpx/')) {
            $content[] = 'This copy of cpx is managed by Composer.';
            $content[] = "Run 'composer global update cpx/cpx' to persist this update.";
        }

        callout(label: 'cpx updated', content: $content);
    }
}
