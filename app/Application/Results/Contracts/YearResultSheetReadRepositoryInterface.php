<?php

namespace App\Application\Results\Contracts;

/**
 * Read side of the annual / GPA sheet: per active student of a class (or section) — how many current term results the
 * year has, the current operational / official annual result and year GPA, the rank in the current class ranking and the
 * current transcript (up to date when issued after the current official GPA).
 */
interface YearResultSheetReadRepositoryInterface
{
    /**
     * @return list<array{
     *     enrollment_id: int, student_name: string, student_code: string, section_name: string,
     *     term_results: array{operational: int, official: int},
     *     annual: array{operational: array{version: int, value: ?string, incomplete: bool}|null, official: array{version: int, value: ?string, incomplete: bool}|null},
     *     gpa: array{operational: array{version: int, value: ?string, incomplete: bool}|null, official: array{version: int, value: ?string, incomplete: bool}|null},
     *     rank: ?int,
     *     transcript: array{number: string, version: int, up_to_date: bool}|null
     * }>
     */
    public function rows(int $schoolId, int $academicYearId, int $classId, ?int $sectionId): array;

    /**
     * The current ranking snapshot of the class (whole class, all sections).
     *
     * @return array{id: int, version: int, participants: int, calculated_at: string}|null
     */
    public function currentRanking(int $schoolId, int $academicYearId, int $classId): ?array;
}
