<?php

namespace App\Domain\Student\Exceptions;

use App\Domain\Shared\Exceptions\SisDomainException;

final class StudentNotFoundException extends SisDomainException
{
    public static function forId(int $studentId): self
    {
        return new self("Student {$studentId} was not found.", 'student.not_found');
    }
}
