<?php

declare(strict_types=1);

use Cpx\Exceptions\SelfUpdateException;
use Cpx\Process\ProcessRunner;
use Cpx\SelfUpdate\PharReplacer;
use Cpx\SelfUpdate\Release;
use Cpx\Support\FilesystemFake;
use Cpx\Support\SilentLogger;

function newPharScript(string $tag = 'v9.9.9'): string
{
    return "<?php echo 'cpx {$tag}';";
}

/** Keep the smoke-test child process output off the console, like the commands do. */
function quietly(Closure $operation): void
{
    ProcessRunner::withLogger(new SilentLogger, $operation);
}

function releaseFor(string $script, string $tag = 'v9.9.9'): Release
{
    return new Release($tag, 'https://github.com/laravel/cpx/releases/download/'.$tag.'/cpx', hash('sha256', $script));
}

function writesScript(string $script): Closure
{
    return function (string $destination) use ($script): void {
        file_put_contents($destination, $script);
    };
}

test('replaces the phar and cleans up the working files', function () {
    $directory = $this->temporaryDirectory();
    $target = "{$directory}/cpx";
    writeExecutable($target, 'old-phar');
    $script = newPharScript();

    quietly(fn () => (new PharReplacer)->replace($target, releaseFor($script), writesScript($script)));

    expect(file_get_contents($target))->toBe($script)
        ->and(glob("{$directory}/*"))->toBe([$target]);
});

test('preserves the executable permissions on the replaced phar', function () {
    $directory = $this->temporaryDirectory();
    $target = "{$directory}/cpx";
    writeExecutable($target, 'old-phar');
    $script = newPharScript();

    quietly(fn () => (new PharReplacer)->replace($target, releaseFor($script), writesScript($script)));

    expect(fileperms($target) & 0777)->toBe(0755);
})->skipOnWindows();

test('replaces via copy in windows mode', function () {
    $directory = $this->temporaryDirectory();
    $target = "{$directory}/cpx";
    writeExecutable($target, 'old-phar');
    $script = newPharScript();

    quietly(fn () => (new PharReplacer(windows: true))->replace($target, releaseFor($script), writesScript($script)));

    expect(file_get_contents($target))->toBe($script)
        ->and(glob("{$directory}/*"))->toBe([$target]);
});

test('skips the checksum verification when the release has no digest', function () {
    $directory = $this->temporaryDirectory();
    $target = "{$directory}/cpx";
    writeExecutable($target, 'old-phar');
    $script = newPharScript();
    $release = new Release('v9.9.9', 'https://github.com/laravel/cpx/releases/download/v9.9.9/cpx');

    quietly(fn () => (new PharReplacer)->replace($target, $release, writesScript($script)));

    expect(file_get_contents($target))->toBe($script);
});

test('throws when the checksum does not match', function () {
    $directory = $this->temporaryDirectory();
    $target = "{$directory}/cpx";
    writeExecutable($target, 'old-phar');
    $release = new Release('v9.9.9', 'https://github.com/laravel/cpx/releases/download/v9.9.9/cpx', hash('sha256', 'something-else'));

    expect(fn () => quietly(fn () => (new PharReplacer)->replace($target, $release, writesScript(newPharScript()))))
        ->toThrow(SelfUpdateException::class, 'The downloaded PHAR does not match the sha256 checksum GitHub published.')
        ->and(file_get_contents($target))->toBe('old-phar')
        ->and(glob("{$directory}/*"))->toBe([$target]);
});

test('throws when the downloaded phar fails to run', function () {
    $directory = $this->temporaryDirectory();
    $target = "{$directory}/cpx";
    writeExecutable($target, 'old-phar');
    $script = '<?php exit(1);';

    expect(fn () => quietly(fn () => (new PharReplacer)->replace($target, releaseFor($script), writesScript($script))))
        ->toThrow(SelfUpdateException::class, 'The downloaded PHAR did not report version v9.9.9 when executed.')
        ->and(file_get_contents($target))->toBe('old-phar')
        ->and(glob("{$directory}/*"))->toBe([$target]);
});

test('throws when the downloaded phar reports the wrong version', function () {
    $directory = $this->temporaryDirectory();
    $target = "{$directory}/cpx";
    writeExecutable($target, 'old-phar');
    $script = newPharScript('v0.0.1');

    expect(fn () => quietly(fn () => (new PharReplacer)->replace($target, releaseFor($script, 'v9.9.9'), writesScript($script))))
        ->toThrow(SelfUpdateException::class, 'The downloaded PHAR did not report version v9.9.9 when executed.')
        ->and(file_get_contents($target))->toBe('old-phar');
});

test('throws before downloading when the phar directory is not writable', function () {
    $directory = $this->temporaryDirectory();
    $target = "{$directory}/cpx";
    writeExecutable($target, 'old-phar');
    chmod($directory, 0555);
    $downloaded = false;

    try {
        expect(function () use ($target, &$downloaded) {
            (new PharReplacer)->replace($target, releaseFor(newPharScript()), function () use (&$downloaded): void {
                $downloaded = true;
            });
        })->toThrow(SelfUpdateException::class, "The cpx PHAR at '{$target}' cannot be replaced.")
            ->and($downloaded)->toBeFalse();
    } finally {
        chmod($directory, 0755);
    }
})->skipOnWindows();

test('restores the backup when the swap fails', function () {
    $directory = $this->temporaryDirectory();
    $target = "{$directory}/cpx";
    writeExecutable($target, 'old-phar');
    $script = newPharScript();

    FilesystemFake::$failingRenames = 1;

    expect(fn () => quietly(fn () => (new PharReplacer)->replace($target, releaseFor($script), writesScript($script))))
        ->toThrow(SelfUpdateException::class, "Unable to replace the cpx PHAR at '{$target}'.")
        ->and(file_get_contents($target))->toBe('old-phar')
        ->and(glob("{$directory}/*"))->toBe([$target]);
});
