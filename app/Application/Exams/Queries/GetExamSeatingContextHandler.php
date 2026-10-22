<?php

namespace App\Application\Exams\Queries;

use App\Application\Exams\Contracts\ExamSeatingReadRepositoryInterface;

final class GetExamSeatingContextHandler
{
    public function __construct(
        private readonly ExamSeatingReadRepositoryInterface $seating,
    ) {}

    /**
     * @return array{classes: list<array{id: int, name: string, sections: list<array{id: int, name: string}>}>, seat_counts: array<int, int>}
     */
    public function handle(GetExamSeatingContextQuery $query): array
    {
        return [
            'classes' => $this->seating->classes($query->schoolId, $query->academicYearId),
            'seat_counts' => $this->seating->activeSeatCounts($query->schoolId, $query->examSessionIds),
        ];
    }
}
