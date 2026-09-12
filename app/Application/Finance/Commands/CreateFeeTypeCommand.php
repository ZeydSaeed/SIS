<?php

namespace App\Application\Finance\Commands;

use App\Application\Contracts\Command;

final readonly class CreateFeeTypeCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public string $code,
        public string $name,
        public string $amount,
        public bool $isRecurring = false,
        public ?string $idempotencyKey = null,
    ) {}
}
