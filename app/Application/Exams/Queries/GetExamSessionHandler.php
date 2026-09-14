<?php

namespace App\Application\Exams\Queries;

use App\Application\Exams\DTOs\ExamSessionDTO;
use App\Domain\Exams\Repositories\ExamRepositoryInterface;

final class GetExamSessionHandler
{
    public function __construct(
        private readonly ExamRepositoryInterface $exams,
    ) {}

    public function handle(GetExamSessionQuery $query): ?ExamSessionDTO
    {
        $row = $this->exams->findSessionByIdAndSchool($query->examSessionId, $query->schoolId);
        if ($row === null) {
            return null;
        }

        return new ExamSessionDTO(
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
        );
    }
}
