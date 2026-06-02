<?php

declare(strict_types=1);

namespace PayGreen\SyliusPayumPlugin\Lock;

interface LockInterface
{
    public function acquire(): bool;

    public function release(): void;
}
