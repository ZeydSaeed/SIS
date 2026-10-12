<?php

namespace App\Domain\Attendance\Repositories;

use App\Domain\Attendance\Data\StaffRecordSnapshot;

interface StaffAttendanceRepositoryInterface
{
    /** SELECT … FOR UPDATE on the teacher's row of that day. */
    public function lockForDay(int $schoolId, int $teacherId, string $workDate): ?StaffRecordSnapshot;

    /**
     * @param  array{status: int, check_in_at: ?string, check_out_at: ?string, minutes_late: ?int, method: int, notes: ?string}  $values
     */
    public function insert(int $schoolId, int $academicYearId, int $teacherId, string $workDate, array $values, ?int $recordedBy): int;

    /**
     * @param  array{status: int, check_in_at: ?string, check_out_at: ?string, minutes_late: ?int, method: int, notes: ?string}  $values
     */
    public function update(int $recordId, int $expectedVersion, array $values, ?int $recordedBy): bool;

    public function teacherBelongsToSchool(int $teacherId, int $schoolId, int $academicYearId): bool;

    /**
     * Hashes of the check-in codes valid at $now (not revoked).
     *
     * @return list<string>
     */
    public function validCodeHashes(int $schoolId, \DateTimeImmutable $now, int $kind = 1): array;

    /** kind 1 = rotating code (shown as text and QR) · 2 = NFC station token (long-lived, written to a tag). */
    public function storeCode(int $schoolId, string $codeHash, \DateTimeImmutable $validFrom, \DateTimeImmutable $validUntil, ?int $createdBy, int $kind = 1, ?string $label = null): int;

    /** Revokes every still-valid code of the school (a new code replaces the previous one). */
    public function revokeCodes(int $schoolId, \DateTimeImmutable $now, int $kind = 1): void;
}
