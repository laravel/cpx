<?php

use Cpx\Input\PackageInvocation;

test('it separates the target from forwarded argv tokens', function () {
    $invocation = PackageInvocation::fromRawTokens(['vendor/package', '--flag', 'value']);

    expect($invocation->target)->toBe('vendor/package')
        ->and($invocation->forwardedTokens())->toBe(['--flag', 'value']);
});

test('it preserves the double-dash separator for the target command', function () {
    $invocation = PackageInvocation::fromRawTokens(['vendor/package', '--', '--literal', '-x']);

    expect($invocation->forwardedTokens())->toBe(['--', '--literal', '-x'])
        ->and($invocation->hasOption('literal'))->toBeFalse();
});

test('it preserves forwarded tokens without shell interpretation', function () {
    $tokens = [
        '--flag',
        '-x',
        '--filter=one',
        '--filter=two',
        'two words',
        '"quoted"',
        'semi;colon',
        'pipe|value',
        '$(touch injected)',
    ];

    $invocation = PackageInvocation::fromRawTokens(['vendor/package', ...$tokens]);

    expect($invocation->forwardedTokens())->toBe($tokens);
});

test('it exposes file fallback options without rebuilding a shell command', function () {
    $invocation = PackageInvocation::fromRawTokens([
        'script.php',
        '--find-autoloader=false',
        '--load-laravel-bootstrap',
        '--alias-classes',
        '0',
    ]);

    expect($invocation->hasOption('find-autoloader'))->toBeTrue()
        ->and($invocation->option('find-autoloader'))->toBe('false')
        ->and($invocation->hasOption('load-laravel-bootstrap'))->toBeTrue()
        ->and($invocation->option('load-laravel-bootstrap'))->toBeNull()
        ->and($invocation->option('alias-classes'))->toBe('0');
});

test('it can consume the first forwarded token for multi-binary packages', function () {
    $invocation = PackageInvocation::fromRawTokens(['vendor/package', 'bin-name', '--flag']);

    expect($invocation->firstForwardedToken())->toBe('bin-name')
        ->and($invocation->withoutFirstForwardedToken()->forwardedTokens())->toBe(['--flag'])
        ->and($invocation->forwardedTokens())->toBe(['bin-name', '--flag']);
});

test('it rejects empty invocations before package execution', function () {
    PackageInvocation::fromRawTokens([]);
})->throws(InvalidArgumentException::class, 'A package invocation target must be provided.');

test('it rejects whitespace-only targets before package execution', function () {
    PackageInvocation::fromRawTokens(['   ']);
})->throws(InvalidArgumentException::class, 'A package invocation target must be provided.');

test('it trims surrounding whitespace from the target', function () {
    $invocation = PackageInvocation::fromRawTokens(['  vendor/package  ', '--flag']);

    expect($invocation->target)->toBe('vendor/package')
        ->and($invocation->forwardedTokens())->toBe(['--flag']);
});
