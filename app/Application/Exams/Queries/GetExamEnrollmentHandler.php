<?php

namespace App\Application\Exams\Queries;

use App\Application\Exams\DTOs\ExamEnrollmentDTO;
use App\Domain\Exams\Repositories\ExamRepositoryInterface;

final class GetExamEnrollmentHandler
{
    public function __construct(
        private readonly ExamRepositoryInterface $exams,
    ) {}

    public function handle(GetExamEnrollmentQuery $query): ?ExamEnrollmentDTO
    {
        $row = $this->exams->findExamEnrollmentByIdAndSchool($query->examEnrollmentId, $query->schoolId);
        if ($row === null) {
            return null;
        }

        return new ExamEnrollmentDTO(
            id: $row->id,
            examSessionId: $row->examSessionId,
            schoolId: $row->schoolId,
            enrollmentId: $row->enrollmentId,
            status: $row->status,
            seatNumber: $row->seatNumber,
        );
    }
}
