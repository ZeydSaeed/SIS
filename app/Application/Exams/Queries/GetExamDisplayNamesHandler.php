<?php

namespace App\Application\Exams\Queries;

use App\Application\Exams\Contracts\ExamDisplayNamesReadRepositoryInterface;

final class GetExamDisplayNamesHandler
{
    public function __construct(
        private readonly ExamDisplayNamesReadRepositoryInterface $names,
    ) {}

    /**
     * @return array{terms: array<int, string>, academic_years: array<int, string>, subjects: array<int, string>}
     */
    public function handle(GetExamDisplayNamesQuery $query): array
    {
        return $this->names->names($query->termIds, $query->academicYearIds, $query->subjectIds);
    }
}
