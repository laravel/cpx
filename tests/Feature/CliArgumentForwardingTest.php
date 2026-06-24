<?php

test('package arguments are forwarded exactly as argv tokens')->todo(
    'Enable when the process runner forwards argv tokens without shell reconstruction.',
);

test('short flags, long flags, repeated options, and option values are preserved')->todo(
    'Enable when forwarded package options are preserved as original argv tokens.',
);

test('a target binary exit code becomes the cpx exit code')->todo(
    'Enable when child process exit codes are propagated through the cpx executable.',
);

test('a missing target binary returns a non-zero status with an actionable error')->todo(
    'Enable when missing binaries produce explicit non-zero failures.',
);
