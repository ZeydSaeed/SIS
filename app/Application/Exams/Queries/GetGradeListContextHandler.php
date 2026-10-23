<?php

namespace App\Application\Exams\Queries;

use App\Application\Exams\Contracts\GradeEntrySheetReadRepositoryInterface;
use App\Application\Exams\Contracts\GradeListStudentsReadRepositoryInterface;

/** «الدرجات» list: the year's sessions to choose from and the names of the students listed. */
final class GetGradeListContextHandler
{
    public function __construct(
        private readonly GradeEntrySheetReadRepositoryInterface $sheet,
        private readonly GradeListStudentsReadRepositoryInterface $students,
    ) {}

    /**
     * @return array{sessions: list<array{id: int, exam_name: string, subject_name: string, session_date: string, status: int, max_grade: int}>,
     *     students: array<int, array{student_name: string, student_code: string}>}
     */
    public function handle(GetGradeListContextQuery $query): array
    {
        return [
            'sessions' => $this->sheet->sessions($query->schoolId, $query->academicYearId, null),
            'students' => $this->students->students($query->schoolId, $query->enrollmentIds),
        ];
    }
}
