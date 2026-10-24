<?php

namespace App\Application\Exams\Queries;

use App\Domain\Exams\Repositories\GradeReviewRepositoryInterface;

final class ListGradeReviewsHandler
{
    public function __construct(
        private readonly GradeReviewRepositoryInterface $reviews,
    ) {}

    /** @return list<array<string, mixed>> */
    public function handle(ListGradeReviewsQuery $query): array
    {
        return $this->reviews->listForYear($query->schoolId, $query->academicYearId, $query->status);
    }
}
