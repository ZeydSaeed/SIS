<?php

namespace App\Domain\Hr\Services;

use App\Domain\Hr\Repositories\HrRepositoryInterface;
use App\Domain\Hr\ValueObjects\JobPositionStatus;

/**
 * RegisterEmployee preconditions — keeps Application handler within ARCH-103.
 */
final class RegisterEmployeeGuard
{
    public function __construct(
        private readonly HrRepositoryInterface $hr,
    ) {}

    public function rejectionCode(
        int $schoolId,
        int $academicYearId,
        string $employeeNumber,
        string $firstName,
        string $lastName,
        ?int $jobPositionId,
        ?int $teacherId,
    ): ?string {
        if ($employeeNumber === '' || $firstName === '' || $lastName === '') {
            return 'hr.employee_identity_invalid';
        }
        if ($academicYearId < 1) {
            return 'hr.academic_year_invalid';
        }
        if ($this->hr->employeeNumberExists($schoolId, $employeeNumber)) {
            return 'hr.employee_number_taken';
        }
        if ($jobPositionId !== null) {
            $position = $this->hr->findJobPosition($schoolId, $jobPositionId);
            if ($position === null || $position->status !== JobPositionStatus::Active) {
                return 'hr.job_position_not_found';
            }
        }
        if ($teacherId === null) {
            return null;
        }
        if ($this->hr->teacherLinkExists($schoolId, $teacherId)) {
            return 'hr.teacher_already_linked';
        }
        if (! $this->hr->teacherBelongsToSchool($teacherId, $schoolId, $academicYearId)) {
            return 'hr.teacher_not_in_school_year';
        }

        return null;
    }
}
