<?php

// The runtime slice runs inside child processes next to project code, so it must stay dependency-free.
arch('the runtime slice stays dependency-free')
    ->expect('Cpx\Runtime')
    ->toOnlyUse('Cpx\Runtime');
