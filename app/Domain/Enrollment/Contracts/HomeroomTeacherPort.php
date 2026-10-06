<?php

namespace App\Domain\Enrollment\Contracts;

/**
 * Read-only view of the Teachers context for section homeroom assignment (رائد الصف).
 */
interface HomeroomTeacherPort
{
    /** True when the teacher is active and a member of the school in that academic year. */
    public function isActiveTeacherOfSchoolYear(int $teacherId, int $schoolId, int $academicYearId): bool;
}
