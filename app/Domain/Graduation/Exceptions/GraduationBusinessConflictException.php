<?php

namespace App\Domain\Graduation\Exceptions;

use DomainException;

final class GraduationBusinessConflictException extends DomainException
{
    public static function duplicateIdentity(): self
    {
        return new self('Official Graduation business identity already exists for this school and enrollment.');
    }

    public static function invalidState(string $detail): self
    {
        return new self($detail);
    }
}
