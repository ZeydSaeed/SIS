<?php

namespace App\Application\Transfers\DTOs;

final readonly class TransferRequestDTO
{
    public function __construct(
        public int $id,
        public int $studentId,
        public int $fromSchoolId,
        public int $toSchoolId,
        public int $fromEnrollmentId,
        public int $academicYearId,
        public ?string $reason,
        public int $status,
        public ?int $requestedBy,
        public string $requestedAt,
        public ?int $approvedBy,
        public ?string $approvedAt,
        public string $createdAt,
    ) {}
}
