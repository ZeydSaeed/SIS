<?php

namespace App\Infrastructure\Persistence\Finance;

use App\Database\SchemaHelper;
use App\Domain\Finance\Data\StudentFeeSnapshot;
use App\Domain\Finance\Repositories\StudentFeeRepositoryInterface;
use App\Domain\Finance\ValueObjects\FeeTypeStatus;
use Illuminate\Support\Facades\DB;

final class EloquentStudentFeeRepository implements StudentFeeRepositoryInterface
{
    public function enrollmentBelongsToSchoolYear(int $enrollmentId, int $schoolId, int $academicYearId): bool
    {
        $this->bindSchool($schoolId);

        return DB::table(SchemaHelper::qualified('enrollment', 'enrollments'))
            ->where('id', $enrollmentId)
            ->where('school_id', $schoolId)
            ->where('academic_year_id', $academicYearId)
            ->exists();
    }

    public function academicYearExists(int $academicYearId): bool
    {
        return DB::table(SchemaHelper::qualified('academic', 'academic_years'))
            ->where('id', $academicYearId)
            ->exists();
    }

    public function findActiveFeeTypeAmount(int $schoolId, int $feeTypeId): ?string
    {
        $this->bindSchool($schoolId);

        $amount = DB::table(SchemaHelper::qualified('finance', 'fee_types'))
            ->where('id', $feeTypeId)
            ->where('school_id', $schoolId)
            ->where('status', FeeTypeStatus::Active)
            ->value('amount');

        return $amount === null ? null : (string) $amount;
    }

    public function findExistingId(
        int $schoolId,
        int $enrollmentId,
        int $feeTypeId,
        int $academicYearId,
    ): ?int {
        $this->bindSchool($schoolId);

        $id = DB::table(SchemaHelper::qualified('finance', 'student_fees'))
            ->where('school_id', $schoolId)
            ->where('enrollment_id', $enrollmentId)
            ->where('fee_type_id', $feeTypeId)
            ->where('academic_year_id', $academicYearId)
            ->value('id');

        return $id === null ? null : (int) $id;
    }

    public function create(
        int $schoolId,
        int $enrollmentId,
        int $feeTypeId,
        int $academicYearId,
        string $amount,
        ?string $dueDate,
        int $status,
        string $createdAt,
    ): int {
        $this->bindSchool($schoolId);

        return (int) DB::table(SchemaHelper::qualified('finance', 'student_fees'))->insertGetId([
            'school_id' => $schoolId,
            'enrollment_id' => $enrollmentId,
            'fee_type_id' => $feeTypeId,
            'academic_year_id' => $academicYearId,
            'amount' => $amount,
            'due_date' => $dueDate,
            'status' => $status,
            'created_at' => $createdAt,
        ]);
    }

    public function findById(int $schoolId, int $studentFeeId): ?StudentFeeSnapshot
    {
        $this->bindSchool($schoolId);

        $row = DB::table(SchemaHelper::qualified('finance', 'student_fees'))
            ->where('school_id', $schoolId)
            ->where('id', $studentFeeId)
            ->first([
                'id',
                'school_id',
                'enrollment_id',
                'fee_type_id',
                'academic_year_id',
                'amount',
                'due_date',
                'status',
                'created_at',
            ]);

        return $row === null ? null : $this->mapRow($row);
    }

    public function updateStatus(int $schoolId, int $studentFeeId, int $status): void
    {
        $this->bindSchool($schoolId);

        DB::table(SchemaHelper::qualified('finance', 'student_fees'))
            ->where('school_id', $schoolId)
            ->where('id', $studentFeeId)
            ->update(['status' => $status]);
    }

    public function findStudentIdByEnrollment(int $schoolId, int $enrollmentId): ?int
    {
        $this->bindSchool($schoolId);

        $id = DB::table(SchemaHelper::qualified('enrollment', 'enrollments'))
            ->where('school_id', $schoolId)
            ->where('id', $enrollmentId)
            ->value('student_id');

        return $id === null ? null : (int) $id;
    }

    public function listBySchool(
        int $schoolId,
        ?int $enrollmentId = null,
        ?int $academicYearId = null,
        ?int $status = null,
    ): array {
        $this->bindSchool($schoolId);

        $q = DB::table(SchemaHelper::qualified('finance', 'student_fees'))
            ->where('school_id', $schoolId)
            ->orderBy('id');

        if ($enrollmentId !== null) {
            $q->where('enrollment_id', $enrollmentId);
        }
        if ($academicYearId !== null) {
            $q->where('academic_year_id', $academicYearId);
        }
        if ($status !== null) {
            $q->where('status', $status);
        }

        return $q->get([
            'id',
            'school_id',
            'enrollment_id',
            'fee_type_id',
            'academic_year_id',
            'amount',
            'due_date',
            'status',
            'created_at',
        ])->map(fn (object $row): StudentFeeSnapshot => $this->mapRow($row))->all();
    }

    private function mapRow(object $row): StudentFeeSnapshot
    {
        return new StudentFeeSnapshot(
            id: (int) $row->id,
            schoolId: (int) $row->school_id,
            enrollmentId: (int) $row->enrollment_id,
            feeTypeId: (int) $row->fee_type_id,
            academicYearId: (int) $row->academic_year_id,
            amount: (string) $row->amount,
            dueDate: $row->due_date !== null ? (string) $row->due_date : null,
            status: (int) $row->status,
            createdAt: (string) $row->created_at,
        );
    }

    private function bindSchool(int $schoolId): void
    {
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);
    }
}
