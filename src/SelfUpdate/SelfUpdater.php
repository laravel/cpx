<?php

declare(strict_types=1);

namespace Cpx\SelfUpdate;

interface SelfUpdater
{
    public function update(): bool;

    public function newVersion(): string;
}
