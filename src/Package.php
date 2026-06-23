<?php

declare(strict_types=1);

namespace Cpx;

use Cpx\Commands\Command;
use InvalidArgumentException;
use RuntimeException;

class Package
{
    protected function __construct(
        public string $vendor,
        public string $name,
        public ?string $version = null,
    ) {}

    public static function parse(string $str): Package
    {
        if (empty($str)) {
            throw new InvalidArgumentException('A package name must be provided.');
        }

        if (! str_contains($str, '/')) {
            throw new InvalidArgumentException('A package name should be in the format "<vendor>/<package>');
        }

        $parts = explode(':', str_replace('@', ':', $str));
        [$vendor, $name] = explode('/', $parts[0]);
        $version = $parts[1] ?? null;

        if ($version === '') {
            $version = null;
        }

        return new Package($vendor, $name, $version);
    }

    public function folder(): string
    {
        return "{$this->vendor}/{$this->name}/{$this->versionName()}";
    }

    public function versionName(): string
    {
        return $this->version ?? 'latest';
    }

    public function fullPackageString(): string
    {
        return "{$this->vendor}/{$this->name}"
            .($this->version ? ':'.$this->version : '');
    }

    public function delete(): void
    {
        Utils::deleteDirectory(cpx_path("{$this->folder()}"));
    }

    public function runCommand(Console $console, bool $autoUpdate = true): void
    {
        $installDir = $this->installOrUpdatePackage($autoUpdate);

        $binScripts = Composer::detectBinFromComposer("{$installDir}/vendor/{$this->vendor}/{$this->name}");

        if (empty($binScripts)) {
            throw new RuntimeException("No bin command found in {$this}.");
        }

        $binScripts = Utils::arrayMapAssoc(fn (int $_, string $value): array => [basename($value) => $value], $binScripts);

        if (count($binScripts) > 1) {
            $possibleCommands = array_values(array_unique(array_filter([
                $console->command,
                $console->arguments[0] ?? null,
                str_contains($console->command, '/') ? Package::parse($console->command)->name : null,
            ])));

            foreach ($possibleCommands as $possibleCommand) {
                if (in_array($possibleCommand, $binScripts, true)) {
                    if (($console->arguments[0] ?? null) === $possibleCommand) {
                        $console->arguments = array_slice($console->arguments, 1);
                    }
                    $command = $possibleCommand;

                    break;
                } elseif (array_key_exists($possibleCommand, $binScripts)) {
                    if (($console->arguments[0] ?? null) === $possibleCommand) {
                        $console->arguments = array_slice($console->arguments, 1);
                    }
                    $command = $binScripts[$possibleCommand];

                    break;
                }
            }

            if (! isset($command)) {
                throw new RuntimeException("More than 1 bin command found for {$this}: ".implode(', ', array_keys($binScripts)).'.');
            }
        } else {
            $command = $binScripts[array_key_first($binScripts)];
        }

        $binPath = "$installDir/vendor/{$this->vendor}/{$this->name}/$command";

        if (file_exists($binPath)) {
            Metadata::open()->updateLastCheckTime($this)->save();

            // Prepare the command to run
            $cmd = "{$binPath} {$console->getCommandInput()}";

            // Use proc_open for better control of the process and to maintain colors and interactivity
            $descriptors = [
                0 => STDIN,
                1 => STDOUT,
                2 => STDERR,
            ];

            printColor("Running {$command} from {$this}");

            $process = proc_open($cmd, $descriptors, $pipes);

            if (is_resource($process)) {
                proc_close($process);
            }
        } else {
            echo "Error: Command $command not found in {$this}.\n";
        }
    }

    public function installOrUpdatePackage(bool $updateCheck = true): string
    {
        $installDir = cpx_path($this->folder());

        if (! is_dir($installDir)) {
            mkdir($installDir, 0755, true);
        }

        if (! is_dir("$installDir/vendor")) {
            printColor("Installing {$this}...");
            file_put_contents("{$installDir}/composer.json", json_encode([
                'name' => "cpx-{$this->vendor}/cpx-{$this->name}",
                'version' => '1.0.0',
                'config' => [
                    'allow-plugins' => true,
                ],
            ]));
            // Composer::runCommand("init --name=cpx-{$package->name} --version=1.0.0 --no-interaction", $installDir);

            if ($this->version === null) {
                Composer::runCommand("require {$this->vendor}/{$this->name} --no-interaction --no-progress", $installDir);
            } else {
                Composer::runCommand("require {$this->vendor}/{$this->name}:{$this->version} --no-interaction --no-progress", $installDir);
            }

            Metadata::open()->updateLastCheckTime($this, 'updated')->save();
        } elseif ($updateCheck && $this->shouldCheckForUpdates()) {
            printColor("Checking for updates for {$this}...");
            $previousVersion = Composer::getCurrentVersion($installDir);
            Composer::runCommand('update', $installDir);
            $newVersion = Composer::getCurrentVersion($installDir);

            if ($previousVersion !== $newVersion) {
                printColor("{$this} was upgraded from $previousVersion to $newVersion.");
            } else {
                printColor("{$this} is already up-to-date.");
            }

            Metadata::open()->updateLastCheckTime($this, 'updated')->save();
        } else {
            printColor("{$this} is already installed and doesn't need updating.");
        }

        return $installDir;
    }

    public function shouldCheckForUpdates(): bool
    {
        $metadata = Metadata::open();
        $packageKey = $this->fullPackageString();

        if (! $metadata->hasPackage($this)) {
            return true;
        }

        $lastUpdatedAt = $metadata->packages[$packageKey]->lastUpdatedAt;

        if ($lastUpdatedAt === null) {
            return true;
        }

        $lastCheck = strtotime($lastUpdatedAt);

        if ($lastCheck === false) {
            return true;
        }

        return (time() - $lastCheck) > 3600; // 1 hour
    }

    public function __toString(): string
    {
        return $this->fullPackageString();
    }
}
