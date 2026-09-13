<?php

namespace App\Application\Finance\Commands;

use App\Application\Contracts\Command;

final readonly class DeactivateFeeTypeCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $feeTypeId,
        public ?string $idempotencyKey,
    ) {}
}
