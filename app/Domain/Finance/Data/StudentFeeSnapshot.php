<?php

namespace App\Domain\Finance\Data;

final readonly class StudentFeeSnapshot
{
    public function __construct(
        public int $id,
        public int $schoolId,
        public int $enrollmentId,
        public int $feeTypeId,
        public int $academicYearId,
        public string $amount,
        public ?string $dueDate,
        public int $status,
        public string $createdAt,
    ) {}
}
