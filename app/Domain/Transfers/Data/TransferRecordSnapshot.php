<?php

namespace App\Domain\Transfers\Data;

final readonly class TransferRecordSnapshot
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
