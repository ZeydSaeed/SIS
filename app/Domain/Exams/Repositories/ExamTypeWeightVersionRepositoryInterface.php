<?php

namespace App\Domain\Exams\Repositories;

/**
 * Per-year weight versions of exam types (exams.exam_type_weight_versions). The effective weight of a type in a year is
 * its newest version, or the base weight of the type when the year has none.
 */
interface ExamTypeWeightVersionRepositoryInterface
{
    public function yearExists(int $academicYearId): bool;

    /** The next version number of the type in the year (call inside the transaction that inserts it). */
    public function nextVersion(int $schoolId, int $examTypeId, int $academicYearId): int;

    public function insert(int $schoolId, int $examTypeId, int $academicYearId, int $versionNo, int $weightPercentage, string $reason, ?int $createdBy): int;

    /**
     * Newest version per exam type of the school in the year.
     *
     * @return array<int, array{version: int, weight_percentage: int}> keyed by exam type id
     */
    public function newestForYear(int $schoolId, int $academicYearId): array;

    /**
     * @return list<array{version_no: int, weight_percentage: int, reason: string, created_at: string, created_by_name: ?string}>
     */
    public function history(int $schoolId, int $examTypeId, int $academicYearId): array;
}
