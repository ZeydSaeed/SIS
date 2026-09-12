<?php

namespace App\Application\Finance\DTOs;

final readonly class FinanceTransactionDTO
{
    public function __construct(
        public int $id,
        public int $schoolId,
        public int $studentId,
        public int $academicYearId,
        public int $transactionType,
        public string $amount,
        public ?string $balanceAfter,
        public ?string $referenceType,
        public ?int $referenceId,
        public ?string $notes,
        public ?int $createdBy,
        public string $createdAt,
    ) {}
}
