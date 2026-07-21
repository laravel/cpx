<?php

use Cpx\Support\Arr;

test('mapWithKeys passes the value first and the key second', function () {
    $result = Arr::mapWithKeys(
        fn (string $value, int $key): array => ["{$key}-{$value}" => $value],
        ['a', 'b'],
    );

    expect($result)->toBe(['0-a' => 'a', '1-b' => 'b']);
});

test('mapWithKeys lets later keys win on collision', function () {
    $result = Arr::mapWithKeys(
        fn (string $value): array => ['key' => $value],
        ['first', 'second'],
    );

    expect($result)->toBe(['key' => 'second']);
});
