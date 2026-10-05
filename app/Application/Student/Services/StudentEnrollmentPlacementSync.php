<?php

namespace App\Application\Student\Services;

use App\Domain\Student\Contracts\StudentEnrollmentPlacementPort;
use App\Domain\Student\Exceptions\InvalidStudentPlacementException;
use App\Domain\Student\Repositories\StudentRepositoryInterface;

/**
 * Keeps the student page and the enrollments page on one placement:
 * - identity fields are read live by the enrollments page (nothing to sync);
 * - a branch / department change on an enrolled student moves the active enrollment
 *   through the enrollment placement flow (history + curriculum), in the same transaction;
 * - the grade (class) of an enrolled student is changed on the enrollments page only,
 *   because it needs a class and section.
 */
final class StudentEnrollmentPlacementSync
{
    public function __construct(
        private readonly StudentRepositoryInterface $students,
        private readonly StudentEnrollmentPlacementPort $enrollments,
    ) {}

    /**
     * Before saving. Rejects a grade change while enrolled.
     *
     * @return array{placement: array{enrollment_id: int, branch_id: int|null, department_id: int|null, grade_level_id: int|null, grade_level_name: string|null}|null, before: array{school_id: int|null, branch_id: int|null, department_id: int|null, grade_level_id: int|null}}
     */
    public function prepare(int $studentId, int $schoolId, ?string $currentGrade, ?string $requestedGrade): array
    {
        $placement = $this->enrollments->activePlacement($studentId, $schoolId);

        // null = the request does not touch the grade.
        if (
            $placement !== null
            && $requestedGrade !== null
            && trim($requestedGrade) !== trim((string) $currentGrade)
        ) {
            throw InvalidStudentPlacementException::gradeLockedByEnrollment();
        }

        return ['placement' => $placement, 'before' => $this->students->placementIds($studentId)];
    }

    /**
     * After saving (inside the same transaction): a changed branch / department follows
     * onto the active enrollment.
     *
     * @param  array{placement: array{enrollment_id: int, branch_id: int|null, department_id: int|null, grade_level_id: int|null, grade_level_name: string|null}|null, before: array{school_id: int|null, branch_id: int|null, department_id: int|null, grade_level_id: int|null}}  $prepared
     */
    public function afterSave(int $studentId, int $schoolId, array $prepared): void
    {
        $placement = $prepared['placement'];
        if ($placement === null) {
            return;
        }

        $after = $this->students->placementIds($studentId);
        $studentChanged = $after['branch_id'] !== $prepared['before']['branch_id']
            || $after['department_id'] !== $prepared['before']['department_id'];
        $differsFromEnrollment = $after['branch_id'] !== $placement['branch_id']
            || $after['department_id'] !== $placement['department_id'];

        if ($studentChanged && $differsFromEnrollment) {
            $this->enrollments->moveToDepartment(
                $placement['enrollment_id'],
                $schoolId,
                $after['branch_id'],
                $after['department_id'],
            );
        }
    }
}
