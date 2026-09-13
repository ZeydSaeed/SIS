<?php

namespace App\Domain\Finance\Repositories;

use App\Domain\Finance\Data\FinanceTransactionSnapshot;

interface FinanceTransactionRepositoryInterface
{
    public function latestBalanceAfter(int $schoolId, int $studentId, int $academicYearId): ?string;

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
    ): int;

    public function findByIdForSchool(int $schoolId, int $transactionId): ?FinanceTransactionSnapshot;

    /**
     * @return list<FinanceTransactionSnapshot>
     */
    public function listBySchool(
        int $schoolId,
        ?int $studentId = null,
        ?int $academicYearId = null,
    ): array;
}
