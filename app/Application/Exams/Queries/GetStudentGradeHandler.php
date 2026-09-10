<?php

namespace App\Application\Exams\Queries;

use App\Application\Exams\Contracts\StudentGradeReadRepositoryInterface;
use App\Application\Exams\DTOs\StudentGradeDTO;
use App\Domain\Exams\Exceptions\GradeNotFoundException;

final class GetStudentGradeHandler
{
    public function __construct(
        private readonly StudentGradeReadRepositoryInterface $grades,
    ) {}

    public function handle(GetStudentGradeQuery $query): StudentGradeDTO
    {
        $grade = $this->grades->findByIdentity($query->gradeId, $query->academicYearId, $query->schoolId);
        if ($grade === null) {
            throw GradeNotFoundException::forIdentity($query->gradeId, $query->academicYearId);
        }

        return $grade;
    }
}
