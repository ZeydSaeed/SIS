<?php

namespace App\Application\Exams\Queries;

use App\Application\Exams\DTOs\ExamEnrollmentDTO;
use App\Domain\Exams\Repositories\ExamRepositoryInterface;

final class ListExamEnrollmentsHandler
{
    public function __construct(
        private readonly ExamRepositoryInterface $exams,
    ) {}

    /**
     * @return list<ExamEnrollmentDTO>
     */
    public function handle(ListExamEnrollmentsQuery $query): array
    {
        $rows = $this->exams->listEnrollmentsForSession(
            $query->examSessionId,
            $query->schoolId,
            $query->status,
        );

        return array_map(
            static fn ($row): ExamEnrollmentDTO => new ExamEnrollmentDTO(
                id: $row->id,
                examSessionId: $row->examSessionId,
                schoolId: $row->schoolId,
                enrollmentId: $row->enrollmentId,
                status: $row->status,
                seatNumber: $row->seatNumber,
            ),
            $rows,
        );
    }
}
