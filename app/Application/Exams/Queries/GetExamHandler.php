<?php

namespace App\Application\Exams\Queries;

use App\Application\Exams\DTOs\ExamDTO;
use App\Domain\Exams\Repositories\ExamRepositoryInterface;

final class GetExamHandler
{
    public function __construct(
        private readonly ExamRepositoryInterface $exams,
    ) {}

    public function handle(GetExamQuery $query): ?ExamDTO
    {
        $row = $this->exams->findByIdAndSchool($query->examId, $query->schoolId);
        if ($row === null) {
            return null;
        }

        return new ExamDTO(
            id: $row->id,
            schoolId: $row->schoolId,
            academicYearId: $row->academicYearId,
            termId: $row->termId,
            examTypeId: $row->examTypeId,
            name: $row->name,
            startDate: $row->startDate,
            endDate: $row->endDate,
            status: $row->status,
        );
    }
}
