<?php

namespace App\Domain\Timetable\Repositories;

/**
 * «تجاهل» / «مراجعة لاحقاً» marks of «اختبار الجدول» issues (timetable.test_marks). A mark is cleared by status,
 * never deleted; one active mark per issue key and school-year.
 */
interface TimetableTestMarkRepositoryInterface
{
    public const IGNORE = 1;

    public const REVIEW = 2;

    /** @return array<string, int> issue key → mark */
    public function active(int $schoolId, int $academicYearId): array;

    /** Replaces the active mark of the key (clears it when $mark is null). */
    public function set(int $schoolId, int $academicYearId, string $issueKey, ?int $mark, ?string $note, ?int $userId, string $at): void;
}
