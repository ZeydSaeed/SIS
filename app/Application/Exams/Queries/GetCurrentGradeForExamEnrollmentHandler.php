<?php

namespace App\Application\Exams\Queries;

use App\Application\Exams\Contracts\StudentGradeReadRepositoryInterface;
use App\Application\Exams\DTOs\StudentGradeDTO;
use App\Domain\Exams\Exceptions\GradeNotFoundException;

final class GetCurrentGradeForExamEnrollmentHandler
{
    public function __construct(
        private readonly StudentGradeReadRepositoryInterface $grades,
    ) {}

    public function handle(GetCurrentGradeForExamEnrollmentQuery $query): StudentGradeDTO
    {
        $grade = $this->grades->findCurrentForExamEnrollment(
            $query->examEnrollmentId,
            $query->academicYearId,
            $query->schoolId,
        );

        if ($grade === null) {
            throw GradeNotFoundException::forIdentity(0, $query->academicYearId);
        }

        return $grade;
    }
}
