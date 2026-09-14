<?php

namespace App\Domain\Transfers\Repositories;

use App\Domain\Transfers\Data\TransferRecordSnapshot;
use App\Domain\Transfers\Data\TransferRequestSnapshot;

interface TransferRepositoryInterface
{
    /**
     * @return array{student_id:int,school_id:int,academic_year_id:int}|null
     */
    public function findEnrollmentContext(int $enrollmentId): ?array;

    public function schoolExists(int $schoolId): bool;

    public function findPendingRequestIdForEnrollment(int $fromSchoolId, int $enrollmentId): ?int;

    public function findRequestForSchool(int $requestId, int $schoolId): ?TransferRequestSnapshot;

    public function createRequest(
        int $studentId,
        int $fromSchoolId,
        int $toSchoolId,
        int $fromEnrollmentId,
        int $academicYearId,
        ?string $reason,
        int $status,
        ?int $requestedBy,
        string $requestedAt,
        string $createdAt,
    ): int;

    /**
     * @return list<TransferRequestSnapshot>
     */
    public function listRequestsForSchool(int $schoolId, ?int $academicYearId, ?int $status): array;

    public function markApproved(int $requestId, int $toSchoolId, ?int $approvedBy, string $approvedAt): bool;

    public function markRejected(int $requestId, int $toSchoolId, ?int $approvedBy, string $approvedAt): bool;

    public function findRecordIdByRequest(int $schoolId, int $transferRequestId): ?int;

    public function markCompleted(int $requestId, int $toSchoolId): bool;

    public function markCancelled(int $requestId, int $schoolId): bool;

    public function insertTransferRecord(
        int $transferRequestId,
        int $studentId,
        int $fromSchoolId,
        int $toSchoolId,
        int $fromEnrollmentId,
        int $toEnrollmentId,
        string $effectiveDate,
        string $completedAt,
        string $createdAt,
    ): int;

    public function updateStudentCurrentSchool(int $studentId, int $toSchoolId): void;

    public function findRecordForSchool(int $schoolId, int $recordId): ?TransferRecordSnapshot;

    /**
     * @return list<TransferRecordSnapshot>
     */
    public function listRecordsForSchool(int $schoolId): array;

    public function markReopened(int $requestId, int $schoolId): bool;
}
