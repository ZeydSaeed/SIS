<?php

namespace App\Application\Exams\Contracts;

/** Student names for the enrollments shown on the grades list (school-scoped). */
interface GradeListStudentsReadRepositoryInterface
{
    /**
     * @param  list<int>  $enrollmentIds
     * @return array<int, array{student_name: string, student_code: string}> keyed by enrollment id
     */
    public function students(int $schoolId, array $enrollmentIds): array;
}
