<?php

namespace App\Application\Exams\Queries;

use App\Application\Exams\Contracts\ExamFormLookupsReadRepositoryInterface;

final class GetExamFormLookupsHandler
{
    public function __construct(
        private readonly ExamFormLookupsReadRepositoryInterface $lookups,
    ) {}

    /**
     * @return array{terms: list<array{id: int, name: string}>, exam_types: list<array{id: int, name: string, weight_percentage: int}>, subjects: list<array{id: int, name: string}>}
     */
    public function handle(GetExamFormLookupsQuery $query): array
    {
        return [
            'terms' => $query->forExam ? $this->lookups->terms($query->academicYearId) : [],
            'exam_types' => $query->forExam ? $this->lookups->examTypes($query->schoolId) : [],
            'subjects' => $query->forSession ? $this->lookups->subjects($query->schoolId, $query->academicYearId) : [],
        ];
    }
}
