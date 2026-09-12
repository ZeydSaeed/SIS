<?php

namespace App\Application\Finance\DTOs;

final readonly class StudentFeeDTO
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
