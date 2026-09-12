<?php

namespace App\Infrastructure\Persistence\Transfers;

use App\Database\SchemaHelper;
use App\Domain\Transfers\Data\TransferRequestSnapshot;
use App\Domain\Transfers\Repositories\TransferRepositoryInterface;
use App\Domain\Transfers\ValueObjects\TransferRequestStatus;
use Illuminate\Support\Facades\DB;

final class EloquentTransferRepository implements TransferRepositoryInterface
{
    public function findEnrollmentContext(int $enrollmentId): ?array
    {
        $row = DB::table(SchemaHelper::qualified('enrollment', 'enrollments'))
            ->where('id', $enrollmentId)
            ->first(['student_id', 'school_id', 'academic_year_id']);

        if ($row === null) {
            return null;
        }

        return [
            'student_id' => (int) $row->student_id,
            'school_id' => (int) $row->school_id,
            'academic_year_id' => (int) $row->academic_year_id,
        ];
    }

    public function schoolExists(int $schoolId): bool
    {
        return DB::table(SchemaHelper::qualified('organization', 'schools'))
            ->where('id', $schoolId)
            ->exists();
    }

    public function findPendingRequestIdForEnrollment(int $fromSchoolId, int $enrollmentId): ?int
    {
        $this->bindSchool($fromSchoolId);

        $id = DB::table(SchemaHelper::qualified('transfers', 'transfer_requests'))
            ->where('from_school_id', $fromSchoolId)
            ->where('from_enrollment_id', $enrollmentId)
            ->where('status', TransferRequestStatus::Pending)
            ->value('id');

        return $id === null ? null : (int) $id;
    }

    public function findRequestForSchool(int $requestId, int $schoolId): ?TransferRequestSnapshot
    {
        $this->bindSchool($schoolId);

        $row = DB::table(SchemaHelper::qualified('transfers', 'transfer_requests'))
            ->where('id', $requestId)
            ->where(function ($q) use ($schoolId): void {
                $q->where('from_school_id', $schoolId)->orWhere('to_school_id', $schoolId);
            })
            ->first();

        return $row === null ? null : $this->map($row);
    }

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
    ): int {
        $this->bindSchool($fromSchoolId);

        return (int) DB::table(SchemaHelper::qualified('transfers', 'transfer_requests'))->insertGetId([
            'student_id' => $studentId,
            'from_school_id' => $fromSchoolId,
            'to_school_id' => $toSchoolId,
            'from_enrollment_id' => $fromEnrollmentId,
            'academic_year_id' => $academicYearId,
            'reason' => $reason,
            'status' => $status,
            'requested_by' => $requestedBy,
            'requested_at' => $requestedAt,
            'created_at' => $createdAt,
        ]);
    }

    public function listRequestsForSchool(int $schoolId, ?int $academicYearId, ?int $status): array
    {
        $this->bindSchool($schoolId);

        $q = DB::table(SchemaHelper::qualified('transfers', 'transfer_requests'))
            ->where(function ($inner) use ($schoolId): void {
                $inner->where('from_school_id', $schoolId)->orWhere('to_school_id', $schoolId);
            })
            ->orderBy('id');

        if ($academicYearId !== null) {
            $q->where('academic_year_id', $academicYearId);
        }
        if ($status !== null) {
            $q->where('status', $status);
        }

        return $q->get()->map(fn (object $row): TransferRequestSnapshot => $this->map($row))->all();
    }

    public function markApproved(int $requestId, int $toSchoolId, ?int $approvedBy, string $approvedAt): bool
    {
        $this->bindSchool($toSchoolId);

        return DB::table(SchemaHelper::qualified('transfers', 'transfer_requests'))
            ->where('id', $requestId)
            ->where('to_school_id', $toSchoolId)
            ->where('status', TransferRequestStatus::Pending)
            ->update([
                'status' => TransferRequestStatus::Approved,
                'approved_by' => $approvedBy,
                'approved_at' => $approvedAt,
            ]) === 1;
    }

    public function markRejected(int $requestId, int $toSchoolId, ?int $approvedBy, string $approvedAt): bool
    {
        $this->bindSchool($toSchoolId);

        return DB::table(SchemaHelper::qualified('transfers', 'transfer_requests'))
            ->where('id', $requestId)
            ->where('to_school_id', $toSchoolId)
            ->where('status', TransferRequestStatus::Pending)
            ->update([
                'status' => TransferRequestStatus::Rejected,
                'approved_by' => $approvedBy,
                'approved_at' => $approvedAt,
            ]) === 1;
    }

    public function findRecordIdByRequest(int $schoolId, int $transferRequestId): ?int
    {
        $this->bindSchool($schoolId);

        $id = DB::table(SchemaHelper::qualified('transfers', 'transfer_records'))
            ->where('transfer_request_id', $transferRequestId)
            ->value('id');

        return $id === null ? null : (int) $id;
    }

    public function markCompleted(int $requestId, int $toSchoolId): bool
    {
        $this->bindSchool($toSchoolId);

        return DB::table(SchemaHelper::qualified('transfers', 'transfer_requests'))
            ->where('id', $requestId)
            ->where('to_school_id', $toSchoolId)
            ->where('status', TransferRequestStatus::Approved)
            ->update([
                'status' => TransferRequestStatus::Completed,
            ]) === 1;
    }

    public function markCancelled(int $requestId, int $schoolId): bool
    {
        $this->bindSchool($schoolId);

        return DB::table(SchemaHelper::qualified('transfers', 'transfer_requests'))
            ->where('id', $requestId)
            ->where(function ($q) use ($schoolId): void {
                $q->where('from_school_id', $schoolId)->orWhere('to_school_id', $schoolId);
            })
            ->whereIn('status', [TransferRequestStatus::Pending, TransferRequestStatus::Approved])
            ->update([
                'status' => TransferRequestStatus::Cancelled,
            ]) === 1;
    }

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
    ): int {
        $this->bindSchool($toSchoolId);

        return (int) DB::table(SchemaHelper::qualified('transfers', 'transfer_records'))->insertGetId([
            'transfer_request_id' => $transferRequestId,
            'student_id' => $studentId,
            'from_school_id' => $fromSchoolId,
            'to_school_id' => $toSchoolId,
            'from_enrollment_id' => $fromEnrollmentId,
            'to_enrollment_id' => $toEnrollmentId,
            'effective_date' => $effectiveDate,
            'completed_at' => $completedAt,
            'created_at' => $createdAt,
        ]);
    }

    public function updateStudentCurrentSchool(int $studentId, int $toSchoolId): void
    {
        DB::table(SchemaHelper::qualified('students', 'students'))
            ->where('id', $studentId)
            ->update([
                'school_id' => $toSchoolId,
                'updated_at' => now(),
            ]);
    }

    private function map(object $row): TransferRequestSnapshot
    {
        return new TransferRequestSnapshot(
            id: (int) $row->id,
            studentId: (int) $row->student_id,
            fromSchoolId: (int) $row->from_school_id,
            toSchoolId: (int) $row->to_school_id,
            fromEnrollmentId: (int) $row->from_enrollment_id,
            academicYearId: (int) $row->academic_year_id,
            reason: $row->reason !== null ? (string) $row->reason : null,
            status: (int) $row->status,
            requestedBy: $row->requested_by !== null ? (int) $row->requested_by : null,
            requestedAt: (string) $row->requested_at,
            approvedBy: $row->approved_by !== null ? (int) $row->approved_by : null,
            approvedAt: $row->approved_at !== null ? (string) $row->approved_at : null,
            createdAt: (string) $row->created_at,
        );
    }

    private function bindSchool(int $schoolId): void
    {
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);
    }
}
