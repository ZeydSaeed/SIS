<?php

namespace App\Domain\Attendance\Exceptions;

use App\Domain\Shared\Exceptions\SisDomainException;

final class DuplicateOpenAttendanceSessionException extends SisDomainException
{
    public static function forNaturalKey(
        int $schoolId,
        int $academicYearId,
        int $sectionId,
        int $subjectId,
        string $sessionDate,
        ?int $periodId,
    ): self {
        $periodLabel = $periodId === null ? 'null' : (string) $periodId;

        return new self(
            "An OPEN attendance session already exists for school {$schoolId}, year {$academicYearId}, "
            ."section {$sectionId}, subject {$subjectId}, date {$sessionDate}, period {$periodLabel}.",
            'attendance.duplicate_open_session_conflict',
        );
    }
}
