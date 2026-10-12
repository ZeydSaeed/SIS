<?php

namespace App\Domain\Attendance\Repositories;

use App\Domain\Attendance\Data\ExcuseSnapshot;

interface AttendanceExcuseRepositoryInterface
{
    public function create(int $schoolId, int $academicYearId, int $studentId, int $enrollmentId, string $dateFrom, string $dateTo, ?int $sessionId, int $excuseType, string $reason, ?int $documentFileId, ?int $submittedBy): int;

    /** SELECT … FOR UPDATE. */
    public function lock(int $excuseId): ?ExcuseSnapshot;

    public function decide(int $excuseId, int $expectedVersion, int $status, ?int $reviewedBy, ?string $note, int $appliedRecords): bool;

    /**
     * Turns the student's unexcused absences («غائب») inside the excuse range (or of its one session) into
     * «غياب بعذر», linking each record to the excuse. Only status 2 rows change; the previous status of each
     * changed row is returned for the audit trail.
     *
     * @return list<array{record_id: int, session_id: int, section_id: int, date: string, previous_status: int, sheet_status: int}>
     */
    public function applyToAbsences(ExcuseSnapshot $excuse, ?int $appliedBy): array;

    /** The session belongs to the school, year and covers the student's enrollment section. */
    public function sessionMatches(int $sessionId, int $schoolId, int $academicYearId, int $sectionId): bool;

    public function documentBelongsToSchool(int $fileId, int $schoolId): bool;
}
