<?php

namespace Tests;

use Cpx\Composer\ComposerRunner;
use Cpx\Packages\BinExecutable;
use Cpx\Process\ProcessRunner;
use Cpx\Runtime\Environment;
use Laravel\Prompts\Output\BufferedConsoleOutput;
use Laravel\Prompts\Prompt;
use Laravel\Prompts\Terminal;
use PHPUnit\Framework\TestCase as BaseTestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionProperty;

abstract class TestCase extends BaseTestCase
{
    /** @var array<string, string|false> */
    private array $environment = [];

    /** @var array<string, string|null> */
    private array $server = [];

    /** @var list<string> */
    private array $temporaryDirectories = [];

    private ?string $workingDirectory = null;

    protected function setUp(): void
    {
        parent::setUp();

        Prompt::interactive(false);
        Prompt::setOutput(new BufferedConsoleOutput);

        (new ReflectionProperty(Prompt::class, 'terminal'))->setValue(null, new Terminal);
        (new ReflectionProperty(Prompt::class, 'shouldFallback'))->setValue(null, false);
        (new ReflectionProperty(Prompt::class, 'fallbacks'))->setValue(null, []);
    }

    protected function tearDown(): void
    {
        ComposerRunner::clearFake();
        Environment::clearFakePharPath();
        BinExecutable::clearFakeWindows();
        ProcessRunner::clearFakeInput();

        if ($this->workingDirectory !== null) {
            chdir($this->workingDirectory);
        }

        foreach (array_reverse($this->temporaryDirectories) as $directory) {
            $this->deleteDirectory($directory);
        }

        foreach ($this->environment as $name => $value) {
            $value === false ? putenv($name) : putenv("{$name}={$value}");
        }

        foreach ($this->server as $name => $value) {
            if ($value === null) {
                unset($_SERVER[$name]);

                continue;
            }

            $_SERVER[$name] = $value;
        }

        parent::tearDown();
    }

    protected function temporaryDirectory(string $prefix = 'cpx-test'): string
    {
        $directory = sys_get_temp_dir().'/'.$prefix.'-'.bin2hex(random_bytes(8));

        mkdir($directory, 0755, true);

        $this->temporaryDirectories[] = $directory;

        return realpath($directory) ?: $directory;
    }

    protected function useIsolatedComposerHome(): string
    {
        $home = $this->temporaryDirectory('cpx-home');
        $composerHome = "{$home}/composer";

        mkdir($composerHome, 0755, true);

        $this->setEnvironmentVariable('HOME', $home);
        $this->setEnvironmentVariable('COMPOSER_HOME', $composerHome);
        $this->setEnvironmentVariable('CPX_HOME', '');
        $this->setEnvironmentVariable('USERPROFILE', '');
        $this->setEnvironmentVariable('HOMEDRIVE', '');
        $this->setEnvironmentVariable('HOMEPATH', '');

        return $composerHome;
    }

    protected function setEnvironmentVariable(string $name, string $value): void
    {
        if (! array_key_exists($name, $this->environment)) {
            $this->environment[$name] = getenv($name);
        }

        if (! array_key_exists($name, $this->server)) {
            $this->server[$name] = $_SERVER[$name] ?? null;
        }

        putenv("{$name}={$value}");
        $_SERVER[$name] = $value;
    }

    protected function useWorkingDirectory(string $directory): void
    {
        $this->workingDirectory ??= getcwd() ?: null;

        chdir($directory);
    }

    protected function prepareLocalProject(?string $binDir = null): string
    {
        $root = $this->temporaryDirectory('cpx-project');

        $composer = $binDir === null ? [] : ['config' => ['bin-dir' => $binDir]];
        file_put_contents("{$root}/composer.json", json_encode($composer, JSON_THROW_ON_ERROR));

        $this->useWorkingDirectory($root);

        return $root;
    }

    protected function writeLocalBinary(string $root, string $name, string $contents, ?string $binDir = null): string
    {
        $directory = "{$root}/".($binDir ?? 'vendor/bin');

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $path = "{$directory}/{$name}";
        writeExecutable($path, $contents);

        if (PHP_OS_FAMILY === 'Windows') {
            file_put_contents("{$path}.bat", "@php \"%~dp0{$name}\" %*\r\n");
        }

        return $path;
    }

    /**
     * @param  list<string>  $bins
     */
    protected function installLocalPackage(string $root, string $package, array $bins, ?string $version = null): void
    {
        [$vendor, $name] = explode('/', $package);
        $directory = "{$root}/vendor/{$vendor}/{$name}";

        mkdir($directory, 0755, true);
        file_put_contents("{$directory}/composer.json", json_encode(['bin' => $bins], JSON_THROW_ON_ERROR));

        if ($version === null) {
            return;
        }

        $this->recordInstalledVersion($root, $package, $version);
    }

    /**
     * @param  list<string>  $packages
     */
    protected function stagingWithPathPackages(array $packages): string
    {
        $repositories = [];

        foreach ($packages as $package) {
            $fixture = $this->temporaryDirectory('cpx-fixture');
            file_put_contents("{$fixture}/composer.json", json_encode([
                'name' => $package,
                'version' => '1.0.0',
            ], JSON_THROW_ON_ERROR));

            $repositories[] = ['type' => 'path', 'url' => $fixture, 'options' => ['symlink' => false]];
        }

        $repositories[] = ['packagist.org' => false];

        $staging = $this->temporaryDirectory('cpx-staging');
        file_put_contents("{$staging}/composer.json", json_encode([
            'repositories' => $repositories,
            'config' => ['allow-plugins' => true],
        ], JSON_THROW_ON_ERROR));

        return $staging;
    }

    /**
     * @return array{staging: string, package: string, pluginClass: string}
     */
    protected function stagingWithPluginPackage(): array
    {
        $suffix = bin2hex(random_bytes(6));
        $package = "cpx-fixture/plugin-{$suffix}";
        $pluginClass = "CpxFixture\\Plugin{$suffix}";

        $fixture = $this->temporaryDirectory('cpx-plugin');
        mkdir("{$fixture}/src", 0755, true);

        file_put_contents("{$fixture}/composer.json", json_encode([
            'name' => $package,
            'version' => '1.0.0',
            'type' => 'composer-plugin',
            'require' => ['composer-plugin-api' => '^2.0'],
            'extra' => ['class' => $pluginClass],
            'autoload' => ['psr-4' => ['CpxFixture\\' => 'src/']],
        ], JSON_THROW_ON_ERROR));

        file_put_contents("{$fixture}/src/Plugin{$suffix}.php", <<<PHP
        <?php

        namespace CpxFixture;

        use Composer\\Composer;
        use Composer\\IO\\IOInterface;
        use Composer\\Plugin\\PluginInterface;

        class Plugin{$suffix} implements PluginInterface
        {
            public function activate(Composer \$composer, IOInterface \$io): void {}

            public function deactivate(Composer \$composer, IOInterface \$io): void {}

            public function uninstall(Composer \$composer, IOInterface \$io): void {}
        }
        PHP);

        $staging = $this->temporaryDirectory('cpx-plugin-staging');
        file_put_contents("{$staging}/composer.json", json_encode([
            'repositories' => [
                ['type' => 'path', 'url' => $fixture, 'options' => ['symlink' => false]],
                ['packagist.org' => false],
            ],
            'config' => ['allow-plugins' => [$package => true]],
        ], JSON_THROW_ON_ERROR));

        return ['staging' => $staging, 'package' => $package, 'pluginClass' => $pluginClass];
    }

    private function recordInstalledVersion(string $root, string $package, string $version): void
    {
        $composerDirectory = "{$root}/vendor/composer";

        if (! is_dir($composerDirectory)) {
            mkdir($composerDirectory, 0755, true);
        }

        $installedFile = "{$composerDirectory}/installed.json";
        $installed = ['packages' => []];

        if (is_file($installedFile)) {
            $decoded = json_decode((string) file_get_contents($installedFile), true);

            if (is_array($decoded) && isset($decoded['packages']) && is_array($decoded['packages'])) {
                $installed = $decoded;
            }
        }

        $installed['packages'][] = ['name' => $package, 'version' => $version];

        file_put_contents($installedFile, json_encode($installed, JSON_THROW_ON_ERROR));
    }

    private function deleteDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }

        rmdir($directory);
    }
}
