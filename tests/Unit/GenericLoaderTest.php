<?php

use Cpx\Runtime\Context;
use Cpx\Runtime\GenericLoader;

test('it supports any project', function () {
    expect((new GenericLoader)->supports(new Context('/somewhere', null)))->toBeTrue();
});

test('it boots without exposing variables', function () {
    expect((new GenericLoader)->boot(new Context('/somewhere', null)))->toBe([]);
});
