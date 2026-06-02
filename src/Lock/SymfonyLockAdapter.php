<?php

declare(strict_types=1);

namespace PayGreen\SyliusPayumPlugin\Lock;

use Symfony\Component\Lock\LockInterface as SymfonyLockInterface;

final class SymfonyLockAdapter implements LockInterface
{
    public function __construct(private readonly SymfonyLockInterface $inner)
    {
    }

    public function acquire(): bool
    {
        return $this->inner->acquire();
    }

    public function release(): void
    {
        $this->inner->release();
    }
}
