<?php

namespace App\Infrastructure\Persistence\Finance;

use App\Database\SchemaHelper;
use App\Domain\Finance\Data\FinanceTransactionSnapshot;
use App\Domain\Finance\Repositories\FinanceTransactionRepositoryInterface;
use Illuminate\Support\Facades\DB;

final class EloquentFinanceTransactionRepository implements FinanceTransactionRepositoryInterface
{
    public function latestBalanceAfter(int $schoolId, int $studentId, int $academicYearId): ?string
    {
        $this->bindSchool($schoolId);

        $value = DB::table(SchemaHelper::qualified('finance', 'transactions'))
            ->where('school_id', $schoolId)
            ->where('student_id', $studentId)
            ->where('academic_year_id', $academicYearId)
            ->orderByDesc('id')
            ->value('balance_after');

        return $value === null ? null : (string) $value;
    }

    public function append(
        int $schoolId,
        int $studentId,
        int $academicYearId,
        int $transactionType,
        string $amount,
        ?string $balanceAfter,
        ?string $referenceType,
        ?int $referenceId,
        ?string $notes,
        ?int $createdBy,
        string $createdAt,
    ): int {
        $this->bindSchool($schoolId);

        return (int) DB::table(SchemaHelper::qualified('finance', 'transactions'))->insertGetId([
            'school_id' => $schoolId,
            'student_id' => $studentId,
            'academic_year_id' => $academicYearId,
            'transaction_type' => $transactionType,
            'amount' => $amount,
            'balance_after' => $balanceAfter,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'notes' => $notes,
            'created_by' => $createdBy,
            'created_at' => $createdAt,
        ]);
    }

    public function findByIdForSchool(int $schoolId, int $transactionId): ?FinanceTransactionSnapshot
    {
        $this->bindSchool($schoolId);

        $row = DB::table(SchemaHelper::qualified('finance', 'transactions'))
            ->where('school_id', $schoolId)
            ->where('id', $transactionId)
            ->first([
                'id',
                'school_id',
                'student_id',
                'academic_year_id',
                'transaction_type',
                'amount',
                'balance_after',
                'reference_type',
                'reference_id',
                'notes',
                'created_by',
                'created_at',
            ]);

        if ($row === null) {
            return null;
        }

        return new FinanceTransactionSnapshot(
            id: (int) $row->id,
            schoolId: (int) $row->school_id,
            studentId: (int) $row->student_id,
            academicYearId: (int) $row->academic_year_id,
            transactionType: (int) $row->transaction_type,
            amount: (string) $row->amount,
            balanceAfter: $row->balance_after !== null ? (string) $row->balance_after : null,
            referenceType: $row->reference_type !== null ? (string) $row->reference_type : null,
            referenceId: $row->reference_id !== null ? (int) $row->reference_id : null,
            notes: $row->notes !== null ? (string) $row->notes : null,
            createdBy: $row->created_by !== null ? (int) $row->created_by : null,
            createdAt: (string) $row->created_at,
        );
    }

    public function listBySchool(
        int $schoolId,
        ?int $studentId = null,
        ?int $academicYearId = null,
    ): array {
        $this->bindSchool($schoolId);

        $q = DB::table(SchemaHelper::qualified('finance', 'transactions'))
            ->where('school_id', $schoolId)
            ->orderBy('id');

        if ($studentId !== null) {
            $q->where('student_id', $studentId);
        }
        if ($academicYearId !== null) {
            $q->where('academic_year_id', $academicYearId);
        }

        return $q->get([
            'id',
            'school_id',
            'student_id',
            'academic_year_id',
            'transaction_type',
            'amount',
            'balance_after',
            'reference_type',
            'reference_id',
            'notes',
            'created_by',
            'created_at',
        ])->map(static function (object $row): FinanceTransactionSnapshot {
            return new FinanceTransactionSnapshot(
                id: (int) $row->id,
                schoolId: (int) $row->school_id,
                studentId: (int) $row->student_id,
                academicYearId: (int) $row->academic_year_id,
                transactionType: (int) $row->transaction_type,
                amount: (string) $row->amount,
                balanceAfter: $row->balance_after !== null ? (string) $row->balance_after : null,
                referenceType: $row->reference_type !== null ? (string) $row->reference_type : null,
                referenceId: $row->reference_id !== null ? (int) $row->reference_id : null,
                notes: $row->notes !== null ? (string) $row->notes : null,
                createdBy: $row->created_by !== null ? (int) $row->created_by : null,
                createdAt: (string) $row->created_at,
            );
        })->all();
    }

    private function bindSchool(int $schoolId): void
    {
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);
    }
}
