<?php

namespace App\Application\Enrollment\Commands;

use App\Application\Contracts\Command;

final readonly class ChangeEnrollmentStatusesCommand implements Command
{
    /**
     * @param  list<int>  $enrollmentIds
     */
    public function __construct(
        public int $schoolId,
        public array $enrollmentIds,
        public int $status,
        public string $effectiveTo,
        public ?int $actedBy = null,
        public ?string $idempotencyKey = null,
    ) {}
}
