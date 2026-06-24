<?php

test('a package with one binary runs without requiring a binary name')->todo(
    'Enable when package binaries are executed through the safe process runner.',
);

test('a package with multiple binaries uses the first forwarded argument as the binary name')->todo(
    'Enable when multiple-binary packages are resolved through validated targets.',
);

test('ambiguous multiple-binary packages list the available binaries')->todo(
    'Enable when multiple-binary packages return actionable ambiguity errors.',
);
