<?php

namespace App\Infrastructure\Persistence\Finance;

use App\Database\SchemaHelper;
use App\Domain\Finance\Data\PaymentSnapshot;
use App\Domain\Finance\Repositories\PaymentRepositoryInterface;
use App\Domain\Finance\ValueObjects\PaymentStatus;
use Illuminate\Support\Facades\DB;

final class EloquentPaymentRepository implements PaymentRepositoryInterface
{
    public function create(
        int $schoolId,
        int $studentFeeId,
        string $amount,
        int $paymentMethod,
        ?string $paymentReference,
        string $idempotencyKey,
        string $paidAt,
        ?int $receivedBy,
        string $createdAt,
    ): int {
        $this->bindSchool($schoolId);

        return (int) DB::table(SchemaHelper::qualified('finance', 'payments'))->insertGetId([
            'school_id' => $schoolId,
            'student_fee_id' => $studentFeeId,
            'amount' => $amount,
            'payment_method' => $paymentMethod,
            'payment_reference' => $paymentReference,
            'idempotency_key' => $idempotencyKey,
            'paid_at' => $paidAt,
            'received_by' => $receivedBy,
            'status' => PaymentStatus::Posted,
            'voided_at' => null,
            'voided_by' => null,
            'created_at' => $createdAt,
        ]);
    }

    public function findByIdForSchool(int $schoolId, int $paymentId): ?PaymentSnapshot
    {
        $this->bindSchool($schoolId);

        $row = DB::table(SchemaHelper::qualified('finance', 'payments'))
            ->where('school_id', $schoolId)
            ->where('id', $paymentId)
            ->first([
                'id',
                'school_id',
                'student_fee_id',
                'amount',
                'payment_method',
                'payment_reference',
                'idempotency_key',
                'paid_at',
                'received_by',
                'status',
                'voided_at',
                'voided_by',
                'created_at',
            ]);

        return $row !== null ? $this->toSnapshot($row) : null;
    }

    public function voidPayment(
        int $schoolId,
        int $paymentId,
        string $voidedAt,
        ?int $voidedBy,
    ): bool {
        $this->bindSchool($schoolId);

        return DB::table(SchemaHelper::qualified('finance', 'payments'))
            ->where('school_id', $schoolId)
            ->where('id', $paymentId)
            ->where('status', PaymentStatus::Posted)
            ->update([
                'status' => PaymentStatus::Voided,
                'voided_at' => $voidedAt,
                'voided_by' => $voidedBy,
            ]) === 1;
    }

    public function restorePayment(int $schoolId, int $paymentId): bool
    {
        $this->bindSchool($schoolId);

        return DB::table(SchemaHelper::qualified('finance', 'payments'))
            ->where('school_id', $schoolId)
            ->where('id', $paymentId)
            ->where('status', PaymentStatus::Voided)
            ->update([
                'status' => PaymentStatus::Posted,
                'voided_at' => null,
                'voided_by' => null,
            ]) === 1;
    }

    public function sumByStudentFee(int $schoolId, int $studentFeeId): string
    {
        $this->bindSchool($schoolId);

        $sum = DB::table(SchemaHelper::qualified('finance', 'payments'))
            ->where('school_id', $schoolId)
            ->where('student_fee_id', $studentFeeId)
            ->where('status', PaymentStatus::Posted)
            ->selectRaw('COALESCE(SUM(amount), 0) as total')
            ->value('total');

        return number_format((float) $sum, 2, '.', '');
    }

    public function listBySchool(int $schoolId, ?int $studentFeeId = null): array
    {
        $this->bindSchool($schoolId);

        $q = DB::table(SchemaHelper::qualified('finance', 'payments'))
            ->where('school_id', $schoolId)
            ->orderBy('id');

        if ($studentFeeId !== null) {
            $q->where('student_fee_id', $studentFeeId);
        }

        return $q->get([
            'id',
            'school_id',
            'student_fee_id',
            'amount',
            'payment_method',
            'payment_reference',
            'idempotency_key',
            'paid_at',
            'received_by',
            'status',
            'voided_at',
            'voided_by',
            'created_at',
        ])->map(fn (object $row): PaymentSnapshot => $this->toSnapshot($row))->all();
    }

    private function toSnapshot(object $row): PaymentSnapshot
    {
        return new PaymentSnapshot(
            id: (int) $row->id,
            schoolId: (int) $row->school_id,
            studentFeeId: (int) $row->student_fee_id,
            amount: (string) $row->amount,
            paymentMethod: (int) $row->payment_method,
            paymentReference: $row->payment_reference !== null ? (string) $row->payment_reference : null,
            idempotencyKey: (string) $row->idempotency_key,
            paidAt: (string) $row->paid_at,
            receivedBy: $row->received_by !== null ? (int) $row->received_by : null,
            status: (int) ($row->status ?? PaymentStatus::Posted),
            voidedAt: $row->voided_at !== null ? (string) $row->voided_at : null,
            voidedBy: $row->voided_by !== null ? (int) $row->voided_by : null,
            createdAt: (string) $row->created_at,
        );
    }

    private function bindSchool(int $schoolId): void
    {
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);
    }
}
