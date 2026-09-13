<?php

namespace App\Application\Finance\Queries;

use App\Application\Finance\DTOs\FinanceTransactionDTO;
use App\Domain\Finance\Repositories\FinanceTransactionRepositoryInterface;

final class GetFinanceTransactionHandler
{
    public function __construct(
        private readonly FinanceTransactionRepositoryInterface $transactions,
    ) {}

    public function handle(GetFinanceTransactionQuery $query): ?FinanceTransactionDTO
    {
        $s = $this->transactions->findByIdForSchool($query->schoolId, $query->transactionId);
        if ($s === null) {
            return null;
        }

        return new FinanceTransactionDTO(
            id: $s->id,
            schoolId: $s->schoolId,
            studentId: $s->studentId,
            academicYearId: $s->academicYearId,
            transactionType: $s->transactionType,
            amount: $s->amount,
            balanceAfter: $s->balanceAfter,
            referenceType: $s->referenceType,
            referenceId: $s->referenceId,
            notes: $s->notes,
            createdBy: $s->createdBy,
            createdAt: $s->createdAt,
        );
    }
}
