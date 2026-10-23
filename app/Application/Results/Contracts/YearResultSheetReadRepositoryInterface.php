<?php

namespace App\Application\Results\Contracts;

/**
 * Read side of the annual / GPA sheet: per active student of a class (or section) — how many current term results the
 * year has, and the current operational / official annual result and year GPA.
 */
interface YearResultSheetReadRepositoryInterface
{
    /**
     * @return list<array{
     *     enrollment_id: int, student_name: string, student_code: string, section_name: string,
     *     term_results: array{operational: int, official: int},
     *     annual: array{operational: array{version: int, value: ?string, incomplete: bool}|null, official: array{version: int, value: ?string, incomplete: bool}|null},
     *     gpa: array{operational: array{version: int, value: ?string, incomplete: bool}|null, official: array{version: int, value: ?string, incomplete: bool}|null}
     * }>
     */
    public function rows(int $schoolId, int $academicYearId, int $classId, ?int $sectionId): array;
}
