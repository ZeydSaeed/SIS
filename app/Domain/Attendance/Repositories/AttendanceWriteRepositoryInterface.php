<?php

namespace App\Domain\Attendance\Repositories;

use App\Domain\Attendance\Data\AttendanceRecordSnapshot;
use App\Domain\Attendance\Data\AttendanceSessionSnapshot;
use App\Domain\Attendance\Data\EnrollmentAttendanceContext;
use App\Domain\Attendance\Data\UpsertAttendanceRecordData;

interface AttendanceWriteRepositoryInterface
{
    public function resolveSchoolIdForSection(int $sectionId): ?int;

    public function academicYearContainsDate(int $academicYearId, string $date): bool;

    public function periodBelongsToSchool(int $periodId, int $schoolId): bool;

    /**
     * Early soft guard for OPEN natural-key conflict (school + year + section + subject + date + period).
     * Database partial UNIQUE remains the concurrency authority.
     */
    public function findDuplicateOpenSession(
        int $schoolId,
        int $academicYearId,
        int $sectionId,
        int $subjectId,
        string $sessionDate,
        ?int $periodId,
    ): ?int;

    public function insertSession(
        int $sectionId,
        int $subjectId,
        int $academicYearId,
        string $sessionDate,
        ?int $periodId,
        int $teacherId,
        int $status,
        int $schoolId,
    ): int;

    public function findSessionById(int $sessionId): ?AttendanceSessionSnapshot;

    /**
     * Re-read session status inside a transaction (fail-closed school join optional via resolvedSchoolId).
     */
    public function lockSessionStatus(int $sessionId): ?AttendanceSessionSnapshot;

    public function loadEnrollmentForMark(int $enrollmentId): ?EnrollmentAttendanceContext;

    /**
     * @param  list<UpsertAttendanceRecordData>  $rows
     */
    public function upsertAttendanceRecords(array $rows): int;

    public function findRecord(
        int $sessionId,
        int $studentId,
        int $academicYearId,
        int $schoolId,
    ): ?AttendanceRecordSnapshot;

    public function updateRecordStatus(
        int $recordId,
        int $academicYearId,
        int $schoolId,
        int $status,
        ?string $notes,
        ?int $recordedBy,
    ): void;

    /**
     * Optimistic OPEN|CLOSED → CANCELLED. Returns previous status when exactly one row updated.
     */
    public function cancelSessionIfOpenOrClosed(int $sessionId): ?int;

    /**
     * Optimistic OPEN→CLOSED. Returns true when exactly one row updated.
     */
    public function closeSessionIfOpen(int $sessionId): bool;

    public function refreshDailySectionSummary(
        int $sectionId,
        int $schoolId,
        int $academicYearId,
        string $attendanceDate,
    ): void;
}
