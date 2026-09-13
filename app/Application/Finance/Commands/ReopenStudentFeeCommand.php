<?php

namespace App\Application\Finance\Commands;

use App\Application\Contracts\Command;

final readonly class ReopenStudentFeeCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $studentFeeId,
        public ?string $idempotencyKey,
    ) {}
}
