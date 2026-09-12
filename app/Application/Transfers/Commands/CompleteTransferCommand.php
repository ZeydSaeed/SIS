<?php

namespace App\Application\Transfers\Commands;

use App\Application\Contracts\Command;

final readonly class CompleteTransferCommand implements Command
{
    public function __construct(
        public int $toSchoolId,
        public int $transferRequestId,
        public int $toClassId,
        public int $toSectionId,
        public string $effectiveDate,
        public ?int $specializationId,
        public ?int $completedBy,
        public ?string $idempotencyKey,
    ) {}
}
