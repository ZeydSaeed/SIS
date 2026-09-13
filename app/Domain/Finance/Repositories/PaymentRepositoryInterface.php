<?php

namespace App\Domain\Finance\Repositories;

use App\Domain\Finance\Data\PaymentSnapshot;

interface PaymentRepositoryInterface
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
    ): int;

    public function findByIdForSchool(int $schoolId, int $paymentId): ?PaymentSnapshot;

    public function voidPayment(
        int $schoolId,
        int $paymentId,
        string $voidedAt,
        ?int $voidedBy,
    ): bool;

    /** Sum of Posted (non-voided) payments for a student fee. */
    public function sumByStudentFee(int $schoolId, int $studentFeeId): string;

    /**
     * @return list<PaymentSnapshot>
     */
    public function listBySchool(
        int $schoolId,
        ?int $studentFeeId = null,
    ): array;
}
