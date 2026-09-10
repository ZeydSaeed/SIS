<?php

namespace App\Domain\Exams\Exceptions;

use App\Domain\Shared\Exceptions\SisDomainException;

final class GradeNotFoundException extends SisDomainException
{
    public static function forIdentity(int $gradeId, int $academicYearId): self
    {
        return new self(
            "Grade {$gradeId} (year {$academicYearId}) was not found.",
            'grades.not_found',
        );
    }
}
