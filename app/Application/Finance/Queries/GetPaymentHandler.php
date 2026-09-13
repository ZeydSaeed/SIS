<?php

namespace App\Application\Finance\Queries;

use App\Application\Finance\DTOs\PaymentDTO;
use App\Domain\Finance\Repositories\PaymentRepositoryInterface;

final class GetPaymentHandler
{
    public function __construct(
        private readonly PaymentRepositoryInterface $payments,
    ) {}

    public function handle(GetPaymentQuery $query): ?PaymentDTO
    {
        $s = $this->payments->findByIdForSchool($query->schoolId, $query->paymentId);
        if ($s === null) {
            return null;
        }

        return new PaymentDTO(
            id: $s->id,
            schoolId: $s->schoolId,
            studentFeeId: $s->studentFeeId,
            amount: $s->amount,
            paymentMethod: $s->paymentMethod,
            paymentReference: $s->paymentReference,
            idempotencyKey: $s->idempotencyKey,
            paidAt: $s->paidAt,
            receivedBy: $s->receivedBy,
            status: $s->status,
            voidedAt: $s->voidedAt,
            voidedBy: $s->voidedBy,
            createdAt: $s->createdAt,
        );
    }
}
