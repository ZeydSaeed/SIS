<?php

namespace App\Application\Exams\Queries;

use App\Application\Exams\Contracts\StudentGradeReadRepositoryInterface;
use App\Application\Exams\DTOs\StudentGradeDTO;

final class ListGradesForExamSessionHandler
{
    public function __construct(
        private readonly StudentGradeReadRepositoryInterface $grades,
    ) {}

    /**
     * @return list<StudentGradeDTO>
     */
    public function handle(ListGradesForExamSessionQuery $query): array
    {
        return $this->grades->listForExamSession(
            $query->examSessionId,
            $query->academicYearId,
            $query->schoolId,
        );
    }
}
