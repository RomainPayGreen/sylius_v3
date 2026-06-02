<?php

declare(strict_types=1);

namespace PayGreen\SyliusPayumPlugin\Lock;

use Symfony\Component\Lock\LockFactory;

final class SymfonyLockFactoryAdapter implements LockFactoryInterface
{
    public function __construct(private readonly LockFactory $inner)
    {
    }

    public function createLock(string $resource, float $ttl, bool $autoRelease): LockInterface
    {
        return new SymfonyLockAdapter($this->inner->createLock($resource, $ttl, $autoRelease));
    }
}
