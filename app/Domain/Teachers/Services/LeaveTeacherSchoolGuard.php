<?php

namespace App\Domain\Teachers\Services;

use App\Domain\Teachers\Repositories\TeacherRepositoryInterface;

/**
 * Leave-school preconditions — keeps Application handler within ARCH-103.
 */
final class LeaveTeacherSchoolGuard
{
    public function __construct(
        private readonly TeacherRepositoryInterface $teachers,
    ) {}

    public function rejectionCode(
        int $teacherId,
        int $schoolId,
        int $academicYearId,
    ): ?string {
        if ($academicYearId < 1) {
            return 'teachers.academic_year_invalid';
        }
        if ($schoolId < 1) {
            return 'teachers.leave_school_invalid';
        }
        if (! $this->teachers->belongsToSchool($teacherId, $schoolId, $academicYearId)) {
            return 'teachers.not_in_school_year';
        }
        if ($this->teachers->isPrimaryInSchool($teacherId, $schoolId, $academicYearId)) {
            return 'teachers.cannot_leave_primary_school';
        }

        return null;
    }
}
