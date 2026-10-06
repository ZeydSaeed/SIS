<?php

namespace App\Infrastructure\Enrollment;

use App\Domain\Enrollment\Contracts\HomeroomTeacherPort;
use App\Domain\Teachers\Repositories\TeacherRepositoryInterface;
use App\Domain\Teachers\ValueObjects\TeacherStatus;

/** Homeroom eligibility through the Teachers repository (no direct cross-context table reads). */
final class TeachersHomeroomTeacherAdapter implements HomeroomTeacherPort
{
    public function __construct(
        private readonly TeacherRepositoryInterface $teachers,
    ) {}

    public function isActiveTeacherOfSchoolYear(int $teacherId, int $schoolId, int $academicYearId): bool
    {
        $teacher = $this->teachers->findInSchool($teacherId, $schoolId, $academicYearId);

        return $teacher !== null && $teacher->status === TeacherStatus::Active;
    }
}
