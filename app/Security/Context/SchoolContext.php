<?php

namespace App\Security\Context;

use App\Security\Context\Exceptions\SchoolContextRequiredException;

final class SchoolContext
{
    private ?int $schoolId = null;

    public function set(?int $schoolId): void
    {
        $this->schoolId = $schoolId;
    }

    public function id(): ?int
    {
        return $this->schoolId;
    }

    public function requireId(): int
    {
        if ($this->schoolId === null) {
            throw new SchoolContextRequiredException('School context is required for this operation.');
        }

        return $this->schoolId;
    }

    public function clear(): void
    {
        $this->schoolId = null;
    }
}
