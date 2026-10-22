<?php

namespace App\Application\Exams\Contracts;

/** Display names for the ids the exam pages show (terms, academic years, subjects — catalogue rows, not school data). */
interface ExamDisplayNamesReadRepositoryInterface
{
    /**
     * @param  list<int>  $termIds
     * @param  list<int>  $academicYearIds
     * @param  list<int>  $subjectIds
     * @return array{terms: array<int, string>, academic_years: array<int, string>, subjects: array<int, string>}
     */
    public function names(array $termIds, array $academicYearIds, array $subjectIds): array;
}
