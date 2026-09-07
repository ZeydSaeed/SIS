<?php

namespace App\Domain\Student\Exceptions;

use App\Domain\Shared\Exceptions\SisDomainException;

final class DuplicateNationalIdException extends SisDomainException
{
    public static function forNationalId(string $nationalId): self
    {
        return new self("National ID {$nationalId} is already registered.", 'student.national_id_exists');
    }
}
