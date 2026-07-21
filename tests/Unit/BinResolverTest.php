<?php

use Cpx\Input\PackageInvocation;
use Cpx\Packages\BinResolver;

test('a bin matching the package name wins without consuming a forwarded token', function () {
    $invocation = new PackageInvocation('vendor/pkg', ['pkg', 'tests/path']);

    $resolved = BinResolver::resolve(['pkg' => 'pkg', 'other' => 'other'], $invocation, 'pkg');

    expect($resolved?->command)->toBe('pkg')
        ->and($resolved?->invocation->forwardedTokens())->toBe(['pkg', 'tests/path']);
});

test('a bin matching the target does not consume a forwarded token of the same name', function () {
    $invocation = new PackageInvocation('tool', ['tool', '--flag']);

    $resolved = BinResolver::resolve(['tool' => 'tool', 'other' => 'other'], $invocation, 'package');

    expect($resolved?->command)->toBe('tool')
        ->and($resolved?->invocation->forwardedTokens())->toBe(['tool', '--flag']);
});

test('a forwarded token selects a bin and is consumed', function () {
    $invocation = new PackageInvocation('vendor/pkg', ['bar', '--flag']);

    $resolved = BinResolver::resolve(['foo' => 'foo', 'bar' => 'bar'], $invocation, 'pkg');

    expect($resolved?->command)->toBe('bar')
        ->and($resolved?->invocation->forwardedTokens())->toBe(['--flag']);
});

test('an unmatched multi-bin package resolves to null', function () {
    $invocation = new PackageInvocation('vendor/pkg', ['--flag']);

    $resolved = BinResolver::resolve(['foo' => 'foo', 'bar' => 'bar'], $invocation, 'pkg');

    expect($resolved)->toBeNull();
});

test('a single bin resolves regardless of candidates', function () {
    $invocation = new PackageInvocation('vendor/pkg', ['anything']);

    $resolved = BinResolver::resolve(['tool' => 'bin/tool'], $invocation, 'pkg');

    expect($resolved?->command)->toBe('bin/tool')
        ->and($resolved?->invocation->forwardedTokens())->toBe(['anything']);
});

test('a pinned bin resolves without consuming forwarded tokens', function () {
    $invocation = new PackageInvocation('tool', ['foo', '--flag']);

    $resolved = BinResolver::resolve(['foo' => 'foo', 'bar' => 'bar'], $invocation, 'pkg', 'bar');

    expect($resolved?->command)->toBe('bar')
        ->and($resolved?->invocation->forwardedTokens())->toBe(['foo', '--flag']);
});
