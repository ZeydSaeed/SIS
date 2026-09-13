<?php

namespace App\Domain\Teachers\Services;

use App\Domain\Teachers\Repositories\TeacherRepositoryInterface;

/**
 * Change employee_code preconditions — keeps Application handler within ARCH-103.
 */
final class ChangeTeacherEmployeeCodeGuard
{
    public function __construct(
        private readonly TeacherRepositoryInterface $teachers,
    ) {}

    public function rejectionCode(int $schoolId, int $teacherId, string $employeeCode): ?string
    {
        if ($employeeCode === '') {
            return 'teachers.employee_code_required';
        }
        if (! $this->teachers->belongsToSchool($teacherId, $schoolId)) {
            return 'teachers.not_found';
        }

        $current = $this->teachers->findInSchool($teacherId, $schoolId);
        if ($current === null) {
            return 'teachers.not_found';
        }
        if (strcasecmp($current->employeeCode, $employeeCode) === 0) {
            return null; // same code — handler treats as success
        }
        if ($this->teachers->employeeCodeTakenByOther($employeeCode, $teacherId)) {
            return 'teachers.employee_code_taken';
        }

        return null;
    }
}
