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
use Cpx\Support\Result;
use Cpx\Support\SilentLogger;
use Cpx\Version;
use Laravel\Prompts\Support\Logger;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\callout;
use function Laravel\Prompts\info;
use function Laravel\Prompts\task;

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

            $this->applyUpdate($release, $target, $json);

            return $this->reportUpdated($output, $current, $release, $target, $json);
        } catch (SelfUpdateException $exception) {
            if (! Interactivity::isInteractive()) {
                return Result::failure($output, $exception->getMessage());
            }

            $exception->render();

            return self::FAILURE;
        }
    }

    /** @throws SelfUpdateException */
    private function applyUpdate(Release $release, string $target, bool $json): void
    {
        $download = fn (string $destination) => $this->releases->download($release, $destination);

        if ($json) {
            ProcessRunner::withLogger(new SilentLogger, fn () => $this->replacer->replace($target, $release, $download));

            return;
        }

        $failure = null;

        task(
            label: "Updating cpx to {$release->tag}",
            callback: function (Logger $logger) use ($release, $target, $download, &$failure): void {
                try {
                    ProcessRunner::withLogger($logger, fn () => $this->replacer->replace($target, $release, $download));
                    $logger->label("cpx was updated to {$release->tag}");
                } catch (SelfUpdateException $exception) {
                    $failure = $exception;
                    $logger->label('cpx could not be updated');
                }
            },
            keepSummary: true,
        );

        if ($failure !== null) {
            throw $failure;
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

        $content = ["Updated cpx from {$current} to {$release->tag}."];

        if (str_contains(Filesystem::normalizePath($target), '/vendor/cpx/cpx/')) {
            $content[] = 'This copy of cpx is managed by Composer.';
            $content[] = "Run 'composer global update cpx/cpx' to persist this update.";
        }

        callout(label: 'cpx updated', content: $content);

        return self::SUCCESS;
    }
}
