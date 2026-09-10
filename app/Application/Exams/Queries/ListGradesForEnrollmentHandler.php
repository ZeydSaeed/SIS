<?php

namespace App\Application\Exams\Queries;

use App\Application\Exams\Contracts\StudentGradeReadRepositoryInterface;
use App\Application\Exams\DTOs\StudentGradeDTO;

final class ListGradesForEnrollmentHandler
{
    public function __construct(
        private readonly StudentGradeReadRepositoryInterface $grades,
    ) {}

    /**
     * @return list<StudentGradeDTO>
     */
    public function handle(ListGradesForEnrollmentQuery $query): array
    {
        return $this->grades->listForEnrollment(
            $query->enrollmentId,
            $query->academicYearId,
            $query->schoolId,
        );
    }
}
