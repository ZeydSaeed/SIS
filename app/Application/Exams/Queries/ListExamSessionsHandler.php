<?php

namespace App\Application\Exams\Queries;

use App\Application\Exams\DTOs\ExamSessionDTO;
use App\Domain\Exams\Repositories\ExamRepositoryInterface;

final class ListExamSessionsHandler
{
    public function __construct(
        private readonly ExamRepositoryInterface $exams,
    ) {}

    /**
     * @return list<ExamSessionDTO>
     */
    public function handle(ListExamSessionsQuery $query): array
    {
        $rows = $this->exams->listSessionsForExam(
            $query->examId,
            $query->schoolId,
            $query->status,
        );

        return array_map(
            static fn ($row): ExamSessionDTO => new ExamSessionDTO(
                id: $row->id,
                examId: $row->examId,
                schoolId: $row->schoolId,
                subjectId: $row->subjectId,
                sessionDate: $row->sessionDate,
                startTime: $row->startTime,
                endTime: $row->endTime,
                roomId: $row->roomId,
                maxGrade: $row->maxGrade,
                passGrade: $row->passGrade,
                status: $row->status,
            ),
            $rows,
        );
    }
}
