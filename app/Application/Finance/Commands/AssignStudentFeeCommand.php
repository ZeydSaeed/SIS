<?php

namespace App\Application\Finance\Commands;

use App\Application\Contracts\Command;

final readonly class AssignStudentFeeCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $enrollmentId,
        public int $feeTypeId,
        public int $academicYearId,
        public ?string $amountOverride = null,
        public ?string $dueDate = null,
        public ?string $idempotencyKey = null,
    ) {}
}
