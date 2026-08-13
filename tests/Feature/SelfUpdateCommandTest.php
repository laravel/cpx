<?php

declare(strict_types=1);

use Cpx\Exceptions\SelfUpdateException;
use Cpx\Runtime\Environment;
use Cpx\SelfUpdate\Release;
use Cpx\SelfUpdate\ReleaseClient;

function selfUpdatePhar(string $tag = 'v9.9.9'): string
{
    return "<?php echo 'cpx {$tag}';";
}

function fakeSelfUpdateRelease(string $script, string $tag = 'v9.9.9', ?string $sha256 = null): void
{
    ReleaseClient::fake(
        latest: fn (): Release => new Release(
            $tag,
            "https://github.com/laravel/cpx/releases/download/{$tag}/cpx",
            $sha256 ?? hash('sha256', $script),
        ),
        download: function (Release $release, string $destination) use ($script): void {
            file_put_contents($destination, $script);
        },
    );
}

/**
 * @param  list<string>  $arguments
 * @return array{0: int, 1: array<string, mixed>}
 */
function runSelfUpdateJson(array $arguments): array
{
    [$status, $output] = runCpxCommand($arguments);

    return [$status, json_decode($output, true, 512, JSON_THROW_ON_ERROR)];
}

test('updates the phar to the latest release', function () {
    $target = $this->temporaryDirectory().'/cpx';
    writeExecutable($target, 'old-phar');
    Environment::fakePharPath($target);
    $script = selfUpdatePhar();
    fakeSelfUpdateRelease($script);

    [$status, $output] = runCpxCommand(['self-update']);

    expect($status)->toBe(0)
        ->and(file_get_contents($target))->toBe($script)
        ->and(substr_count($output, 'Updated cpx from dev to v9.9.9.'))->toBe(1);
});

test('outputs the update summary as json', function () {
    $target = $this->temporaryDirectory().'/cpx';
    writeExecutable($target, 'old-phar');
    Environment::fakePharPath($target);
    $script = selfUpdatePhar();
    fakeSelfUpdateRelease($script);

    [$status, $payload] = runSelfUpdateJson(['self-update', '--json']);

    expect($status)->toBe(0)
        ->and(file_get_contents($target))->toBe($script)
        ->and($payload)->toBe([
            'success' => true,
            'errors' => [],
            'summary' => ['updated' => true, 'from' => 'dev', 'to' => 'v9.9.9', 'path' => $target],
        ]);
});

test('notes the composer update path for composer-managed installations', function () {
    $directory = $this->temporaryDirectory().'/vendor/cpx/cpx/builds';
    mkdir($directory, 0755, true);
    $target = "{$directory}/cpx";
    writeExecutable($target, 'old-phar');
    Environment::fakePharPath($target);
    $script = selfUpdatePhar();
    fakeSelfUpdateRelease($script);

    [$status, $output] = runCpxCommand(['self-update']);

    expect($status)->toBe(0)
        ->and(file_get_contents($target))->toBe($script)
        ->and($output)->toContain("Run 'composer global update cpx/cpx'");
});

test('reports when cpx is already the latest version', function () {
    $target = $this->temporaryDirectory().'/cpx';
    writeExecutable($target, 'old-phar');
    Environment::fakePharPath($target);
    ReleaseClient::fake(
        latest: fn (): Release => new Release('dev', 'https://github.com/laravel/cpx/releases/download/dev/cpx'),
    );

    [$status, $output] = runCpxCommand(['self-update']);

    expect($status)->toBe(0)
        ->and(file_get_contents($target))->toBe('old-phar')
        ->and($output)->toContain('cpx dev is already the latest version.');
});

test('reports an already up-to-date phar as json', function () {
    $target = $this->temporaryDirectory().'/cpx';
    writeExecutable($target, 'old-phar');
    Environment::fakePharPath($target);
    ReleaseClient::fake(
        latest: fn (): Release => new Release('dev', 'https://github.com/laravel/cpx/releases/download/dev/cpx'),
    );

    [$status, $payload] = runSelfUpdateJson(['self-update', '--json']);

    expect($status)->toBe(0)
        ->and($payload)->toBe([
            'success' => true,
            'errors' => [],
            'summary' => ['updated' => false, 'from' => 'dev', 'to' => 'dev', 'path' => $target],
        ]);
});

test('fails with guidance when cpx is not running from a phar', function () {
    $checked = false;
    ReleaseClient::fake(latest: function () use (&$checked): Release {
        $checked = true;

        return new Release('v9.9.9', 'https://github.com/laravel/cpx/releases/download/v9.9.9/cpx');
    });

    [$status, $output] = runCpxCommand(['self-update']);

    expect($status)->toBe(1)
        ->and($checked)->toBeFalse()
        ->and($output)->toContain('running as a PHAR')
        ->and($output)->toContain('composer global update cpx/cpx');
});

test('keeps the failure guidance in the json envelope', function () {
    [$status, $payload] = runSelfUpdateJson(['self-update', '--json']);

    expect($status)->toBe(1)
        ->and($payload)->toBe([
            'success' => false,
            'errors' => [
                'self-update is only available when cpx is running as a PHAR.',
                'Update a Composer-managed installation with:',
                'composer global update cpx/cpx',
            ],
            'summary' => [],
        ]);
});

test('renders the failure when the release lookup is rate limited', function () {
    $target = $this->temporaryDirectory().'/cpx';
    writeExecutable($target, 'old-phar');
    Environment::fakePharPath($target);
    ReleaseClient::fake(latest: fn (): Release => throw SelfUpdateException::rateLimited());

    [$status, $output] = runCpxCommand(['self-update']);

    expect($status)->toBe(1)
        ->and($output)->toContain('GitHub rate limit exceeded')
        ->and($output)->toContain('GITHUB_TOKEN');
});

test('outputs the failure as a json envelope', function () {
    $target = $this->temporaryDirectory().'/cpx';
    writeExecutable($target, 'old-phar');
    Environment::fakePharPath($target);
    ReleaseClient::fake(latest: fn (): Release => throw SelfUpdateException::rateLimited());

    [$status, $payload] = runSelfUpdateJson(['self-update', '--json']);

    expect($status)->toBe(1)
        ->and($payload)->toBe([
            'success' => false,
            'errors' => [
                'GitHub rate limit exceeded while checking for a new cpx version.',
                'Set the GITHUB_TOKEN environment variable to authenticate and raise the limit.',
            ],
            'summary' => [],
        ]);
});

test('keeps the current phar when the checksum does not match', function () {
    $target = $this->temporaryDirectory().'/cpx';
    writeExecutable($target, 'old-phar');
    Environment::fakePharPath($target);
    fakeSelfUpdateRelease(selfUpdatePhar(), sha256: hash('sha256', 'tampered'));

    [$status, $output] = runCpxCommand(['self-update']);

    expect($status)->toBe(1)
        ->and(file_get_contents($target))->toBe('old-phar')
        ->and($output)->toContain('does not match the sha256 checksum');
});

test('lists self-update as a built-in command', function () {
    [$status, $output] = runCpxCommand(['list']);

    expect($status)->toBe(0)
        ->and($output)->toContain('self-update');
});
