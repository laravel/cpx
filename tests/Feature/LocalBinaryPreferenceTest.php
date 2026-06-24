<?php

test('it runs a local vendor bin before installing an isolated package')->todo(
    'Enable when local project binaries are resolved before isolated package installs.',
);

test('it discovers a custom composer bin-dir before remote package resolution')->todo(
    'Enable when local Composer bin-dir discovery is implemented.',
);

test('local binary execution preserves forwarded arguments and exit codes')->todo(
    'Enable when local binary execution uses argv tokens and propagates exit codes.',
);

test('missing local binaries fall back to alias or package resolution')->todo(
    'Enable when local binary lookup has a documented remote fallback path.',
);
