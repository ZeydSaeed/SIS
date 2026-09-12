<?php

namespace App\Application\Transfers\Commands;

use App\Application\Contracts\Command;

final readonly class CreateTransferRequestCommand implements Command
{
    public function __construct(
        public int $fromSchoolId,
        public int $toSchoolId,
        public int $fromEnrollmentId,
        public int $academicYearId,
        public ?string $reason,
        public ?int $requestedBy,
        public ?string $idempotencyKey,
    ) {}
}
