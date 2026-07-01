<?php

use Cpx\Support\Lock;

test('it runs a critical section and returns its result', function () {
    $lockFile = $this->temporaryDirectory('cpx-lock').'/cpx.lock';

    $result = Lock::run($lockFile, fn () => 'done');

    expect($result)->toBe('done');
});

test('it releases the lock so a later acquisition succeeds', function () {
    $lockFile = $this->temporaryDirectory('cpx-lock').'/cpx.lock';

    $first = Lock::run($lockFile, fn () => 'first');
    $second = Lock::run($lockFile, fn () => 'second');

    expect($first)->toBe('first')
        ->and($second)->toBe('second');
});
