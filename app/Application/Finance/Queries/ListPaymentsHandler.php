<?php

namespace App\Application\Finance\Queries;

use App\Application\Finance\DTOs\PaymentDTO;
use App\Domain\Finance\Repositories\PaymentRepositoryInterface;

final class ListPaymentsHandler
{
    public function __construct(
        private readonly PaymentRepositoryInterface $payments,
    ) {}

    /**
     * @return list<PaymentDTO>
     */
    public function handle(ListPaymentsQuery $query): array
    {
        return array_map(
            static fn ($s): PaymentDTO => new PaymentDTO(
                id: $s->id,
                schoolId: $s->schoolId,
                studentFeeId: $s->studentFeeId,
                amount: $s->amount,
                paymentMethod: $s->paymentMethod,
                paymentReference: $s->paymentReference,
                idempotencyKey: $s->idempotencyKey,
                paidAt: $s->paidAt,
                receivedBy: $s->receivedBy,
                createdAt: $s->createdAt,
            ),
            $this->payments->listBySchool($query->schoolId, $query->studentFeeId),
        );
    }
}
