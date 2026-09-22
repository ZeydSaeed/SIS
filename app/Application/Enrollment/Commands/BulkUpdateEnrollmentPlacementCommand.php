<?php

namespace App\Application\Enrollment\Commands;

use App\Application\Contracts\Command;

final readonly class BulkUpdateEnrollmentPlacementCommand implements Command
{
    /**
     * @param  list<int>  $enrollmentIds
     */
    public function __construct(
        public int $schoolId,
        public array $enrollmentIds,
        public int $classId,
        public int $sectionId,
        public ?int $branchId = null,
        public ?int $departmentId = null,
        public ?int $specializationId = null,
        public ?int $updatedBy = null,
        public ?string $idempotencyKey = null,
    ) {}
}
