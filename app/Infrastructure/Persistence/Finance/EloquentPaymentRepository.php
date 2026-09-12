<?php

namespace App\Infrastructure\Persistence\Finance;

use App\Database\SchemaHelper;
use App\Domain\Finance\Data\PaymentSnapshot;
use App\Domain\Finance\Repositories\PaymentRepositoryInterface;
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
            'created_at' => $createdAt,
        ]);
    }

    public function sumByStudentFee(int $schoolId, int $studentFeeId): string
    {
        $this->bindSchool($schoolId);

        $sum = DB::table(SchemaHelper::qualified('finance', 'payments'))
            ->where('school_id', $schoolId)
            ->where('student_fee_id', $studentFeeId)
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
            'created_at',
        ])->map(static function (object $row): PaymentSnapshot {
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
                createdAt: (string) $row->created_at,
            );
        })->all();
    }

    private function bindSchool(int $schoolId): void
    {
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);
    }
}
