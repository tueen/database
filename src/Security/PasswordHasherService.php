<?php

declare(strict_types=1);

namespace Tueen\Database\Security;

use Symfony\Component\PasswordHasher\Hasher\NativePasswordHasher;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;

final class PasswordHasherService
{
    private PasswordHasherInterface $hasher;

    public function __construct(?PasswordHasherInterface $hasher = null)
    {
        $this->hasher = $hasher ?? new NativePasswordHasher();
    }

    public function hash(string $plainPassword): string
    {
        return $this->hasher->hash($plainPassword);
    }

    public function verify(string $hashedPassword, string $plainPassword): bool
    {
        return $this->hasher->verify($hashedPassword, $plainPassword);
    }

    public function needsRehash(string $hashedPassword): bool
    {
        return $this->hasher->needsRehash($hashedPassword);
    }
}
