<?php

namespace App\Domain\Teachers\Services;

use App\Domain\Teachers\Repositories\TeacherRepositoryInterface;

/**
 * Set primary school preconditions — keeps Application handler within ARCH-103.
 */
final class SetTeacherPrimarySchoolGuard
{
    public function __construct(
        private readonly TeacherRepositoryInterface $teachers,
    ) {}

    public function rejectionCode(
        int $teacherId,
        int $sourceSchoolId,
        int $targetSchoolId,
        int $academicYearId,
    ): ?string {
        if ($academicYearId < 1) {
            return 'teachers.academic_year_invalid';
        }
        if ($sourceSchoolId < 1 || $targetSchoolId < 1) {
            return 'teachers.set_primary_invalid';
        }
        if ($sourceSchoolId === $targetSchoolId) {
            return 'teachers.set_primary_same_school';
        }
        if (! $this->teachers->belongsToSchool($teacherId, $sourceSchoolId, $academicYearId)) {
            return 'teachers.not_in_source_school_year';
        }
        if (! $this->teachers->belongsToSchool($teacherId, $targetSchoolId, $academicYearId)) {
            return 'teachers.not_in_target_school_year';
        }
        if ($this->teachers->isPrimaryInSchool($teacherId, $targetSchoolId, $academicYearId)) {
            return null; // already primary at target — handler success path
        }
        if (! $this->teachers->isPrimaryInSchool($teacherId, $sourceSchoolId, $academicYearId)) {
            return 'teachers.source_not_primary';
        }

        return null;
    }
}
