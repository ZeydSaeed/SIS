<?php

namespace App\Application\Transfers\DTOs;

final readonly class TransferRecordDTO
{
    public function __construct(
        public int $id,
        public int $transferRequestId,
        public int $studentId,
        public int $fromSchoolId,
        public int $toSchoolId,
        public int $fromEnrollmentId,
        public int $toEnrollmentId,
        public string $effectiveDate,
        public string $completedAt,
        public string $createdAt,
    ) {}
}
