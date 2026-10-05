<?php

namespace App\Application\Admission\Commands;

use App\Application\Contracts\Command;

final readonly class TransferApplicationCommand implements Command
{
    /**
     * @param  int  $schoolId  Current school context (the application's school).
     * @param  list<int>  $allowedSchoolIds  Active schools the user is linked to (resolved server-side).
     */
    public function __construct(
        public int $schoolId,
        public int $applicationId,
        public int $toSchoolId,
        public int $toRequestKind,
        public int $toPeriodId,
        public ?string $reason,
        public ?int $transferredBy,
        public array $allowedSchoolIds,
        public ?string $idempotencyKey,
    ) {}
}
