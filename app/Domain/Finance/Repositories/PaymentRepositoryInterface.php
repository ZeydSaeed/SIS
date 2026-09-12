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

    public function sumByStudentFee(int $schoolId, int $studentFeeId): string;

    /**
     * @return list<PaymentSnapshot>
     */
    public function listBySchool(
        int $schoolId,
        ?int $studentFeeId = null,
    ): array;
}
