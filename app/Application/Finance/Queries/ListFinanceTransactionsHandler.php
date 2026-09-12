<?php

namespace App\Application\Finance\Queries;

use App\Application\Finance\DTOs\FinanceTransactionDTO;
use App\Domain\Finance\Repositories\FinanceTransactionRepositoryInterface;

final class ListFinanceTransactionsHandler
{
    public function __construct(
        private readonly FinanceTransactionRepositoryInterface $transactions,
    ) {}

    /**
     * @return list<FinanceTransactionDTO>
     */
    public function handle(ListFinanceTransactionsQuery $query): array
    {
        return array_map(
            static fn ($s): FinanceTransactionDTO => new FinanceTransactionDTO(
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
            ),
            $this->transactions->listBySchool($query->schoolId, $query->studentId, $query->academicYearId),
        );
    }
}
