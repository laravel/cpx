<?php

declare(strict_types=1);

use Cpx\Commands\UpgradeCommand;
use Cpx\Runtime\Environment;
use Cpx\SelfUpdate\SelfUpdater;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

function fakeSelfUpdater(bool $updated, string $newVersion = 'v2.0.0', ?Throwable $error = null): SelfUpdater
{
    return new class($updated, $newVersion, $error) implements SelfUpdater
    {
        public function __construct(
            private bool $updated,
            private string $newVersion,
            private ?Throwable $error,
        ) {}

        public function update(): bool
        {
            if ($this->error !== null) {
                throw $this->error;
            }

            return $this->updated;
        }

        public function newVersion(): string
        {
            return $this->newVersion;
        }
    };
}

test('it explains self-update is only for the phar build and does not update from source', function () {
    $neverUpdates = fakeSelfUpdater(updated: false, error: new RuntimeException('should not update from source'));

    $tester = new CommandTester(new UpgradeCommand($neverUpdates));
    $status = $tester->execute([]);

    expect($status)->toBe(Command::SUCCESS)
        ->and($tester->getDisplay())->toContain('phar');
});

test('it reports the new version after a successful update', function () {
    Environment::fakePharPath('/opt/cpx.phar');

    $tester = new CommandTester(new UpgradeCommand(fakeSelfUpdater(updated: true, newVersion: 'v2.1.0')));
    $status = $tester->execute([]);

    expect($status)->toBe(Command::SUCCESS)
        ->and($tester->getDisplay())->toContain('v2.1.0');
});

test('it reports when cpx is already up-to-date', function () {
    Environment::fakePharPath('/opt/cpx.phar');

    $tester = new CommandTester(new UpgradeCommand(fakeSelfUpdater(updated: false)));
    $status = $tester->execute([]);

    expect($status)->toBe(Command::SUCCESS)
        ->and($tester->getDisplay())->toContain('up-to-date');
});

test('it fails with a clear message when the update throws', function () {
    Environment::fakePharPath('/opt/cpx.phar');

    $failing = fakeSelfUpdater(updated: false, error: new RuntimeException('network unreachable'));

    $tester = new CommandTester(new UpgradeCommand($failing));
    $status = $tester->execute([]);

    expect($status)->toBe(Command::FAILURE)
        ->and($tester->getDisplay())->toContain('network unreachable');
});
