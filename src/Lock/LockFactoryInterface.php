<?php

declare(strict_types=1);

namespace PayGreen\SyliusPayumPlugin\Lock;

interface LockFactoryInterface
{
    public function createLock(string $resource, float $ttl, bool $autoRelease): LockInterface;
}
