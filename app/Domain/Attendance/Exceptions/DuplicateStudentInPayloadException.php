<?php

namespace App\Domain\Attendance\Exceptions;

use App\Domain\Shared\Exceptions\SisDomainException;

final class DuplicateStudentInPayloadException extends SisDomainException
{
    public static function create(): self
    {
        return new self(
            'Duplicate student IDs in attendance payload are not allowed.',
            'attendance.duplicate_student_in_payload',
        );
    }
}
