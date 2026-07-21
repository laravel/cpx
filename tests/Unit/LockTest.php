<?php

use Cpx\Support\Lock;

test('it runs a critical section and returns its result', function () {
    $lockFile = $this->temporaryDirectory('cpx-lock').'/cpx.lock';

    $result = Lock::run($lockFile, fn () => 'done');

    expect($result)->toBe('done');
});

test('the lock excludes a second handle while held and frees it after release', function () {
    $lockFile = $this->temporaryDirectory('cpx-lock').'/cpx.lock';

    $heldDuring = null;

    Lock::run($lockFile, function () use ($lockFile, &$heldDuring): void {
        $probe = fopen($lockFile, 'c');
        assert(is_resource($probe));

        $heldDuring = flock($probe, LOCK_EX | LOCK_NB);
        fclose($probe);
    });

    $probe = fopen($lockFile, 'c');
    assert(is_resource($probe));
    $freedAfter = flock($probe, LOCK_EX | LOCK_NB);
    flock($probe, LOCK_UN);
    fclose($probe);

    expect($heldDuring)->toBeFalse()
        ->and($freedAfter)->toBeTrue();
});

test('it releases the lock so a later acquisition succeeds', function () {
    $lockFile = $this->temporaryDirectory('cpx-lock').'/cpx.lock';

    $first = Lock::run($lockFile, fn () => 'first');
    $second = Lock::run($lockFile, fn () => 'second');

    expect($first)->toBe('first')
        ->and($second)->toBe('second');
});
