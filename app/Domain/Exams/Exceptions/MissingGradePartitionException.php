<?php

namespace App\Domain\Exams\Exceptions;

use App\Domain\Shared\Exceptions\SisDomainException;

final class MissingGradePartitionException extends SisDomainException
{
    public static function forAcademicYear(int $academicYearId): self
    {
        return new self(
            "No student_grades partition exists for academic year {$academicYearId}.",
            'grades.partition_missing',
        );
    }
}
