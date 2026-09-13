<?php

namespace App\Domain\Teachers\Services;

use App\Domain\Teachers\Repositories\TeacherRepositoryInterface;

/**
 * Multi-school assign preconditions — keeps Application handler within ARCH-103.
 */
final class AssignTeacherSchoolGuard
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
            return 'teachers.assign_school_invalid';
        }
        if ($sourceSchoolId === $targetSchoolId) {
            return 'teachers.assign_school_same_school';
        }
        if (! $this->teachers->belongsToSchool($teacherId, $sourceSchoolId)) {
            return 'teachers.not_in_source_school';
        }
        if ($this->teachers->belongsToSchool($teacherId, $targetSchoolId, $academicYearId)) {
            return 'teachers.already_in_target_school_year';
        }

        return null;
    }
}
