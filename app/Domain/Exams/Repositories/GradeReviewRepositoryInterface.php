<?php

namespace App\Domain\Exams\Repositories;

/**
 * Grade reviews (exams.grade_reviews). A review row is a request plus a lifecycle; the grade itself is only ever
 * changed by the existing correction command.
 *
 * @phpstan-type Review array{
 *     id: int, review_number: string, school_id: int, academic_year_id: int, grade_id: int, reason: string,
 *     status: string, requested_by: ?int, assigned_to: ?int, decided_by: ?int, decision_note: ?string,
 *     resulting_grade_id: ?int, requested_at: string, decided_at: ?string
 * }
 */
interface GradeReviewRepositoryInterface
{
    /** @return int the new review id */
    public function insert(int $schoolId, int $academicYearId, int $gradeId, ?string $scoreAtRequest, bool $absentAtRequest, string $reason, ?int $requestedBy, ?string $correlationId): int;

    public function hasOpenFor(int $schoolId, int $gradeId): bool;

    /** @return Review|null locked FOR UPDATE inside the caller's transaction */
    public function lock(int $schoolId, int $reviewId): ?array;

    public function start(int $reviewId, int $assignedTo): void;

    public function accept(int $reviewId, int $decidedBy, string $note, int $resultingGradeId): void;

    public function reject(int $reviewId, int $decidedBy, string $note): void;

    public function withdraw(int $reviewId): void;

    /**
     * @return list<array<string, mixed>> reviews of the year (newest first), with student, subject and user names
     */
    public function listForYear(int $schoolId, int $academicYearId, ?string $status): array;
}
