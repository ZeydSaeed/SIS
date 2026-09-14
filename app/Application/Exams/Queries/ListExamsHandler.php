<?php

namespace App\Application\Exams\Queries;

use App\Application\Exams\DTOs\ExamDTO;
use App\Domain\Exams\Repositories\ExamRepositoryInterface;

final class ListExamsHandler
{
    public function __construct(
        private readonly ExamRepositoryInterface $exams,
    ) {}

    /**
     * @return list<ExamDTO>
     */
    public function handle(ListExamsQuery $query): array
    {
        $rows = $this->exams->listBySchool(
            $query->schoolId,
            $query->academicYearId,
            $query->status,
        );

        return array_map(
            static fn ($row): ExamDTO => new ExamDTO(
                id: $row->id,
                schoolId: $row->schoolId,
                academicYearId: $row->academicYearId,
                termId: $row->termId,
                examTypeId: $row->examTypeId,
                name: $row->name,
                startDate: $row->startDate,
                endDate: $row->endDate,
                status: $row->status,
            ),
            $rows,
        );
    }
}
