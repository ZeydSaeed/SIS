<?php

namespace App\Application\Enrollment\Commands;

use App\Application\Contracts\Command;

final readonly class UpdateEnrollmentPlacementCommand implements Command
{
    public function __construct(
        public int $enrollmentId,
        public int $schoolId,
        public int $classId,
        public int $sectionId,
        public ?int $specializationId = null,
        public ?int $branchId = null,
        public bool $updateBranch = false,
        public ?int $departmentId = null,
        public bool $updateDepartment = false,
        public ?string $effectiveFrom = null,
        public ?int $academicYearId = null,
        public ?string $effectiveTo = null,
        public bool $clearEffectiveTo = false,
        public ?string $stageName = null,
        public bool $updateStage = false,
        public ?int $gender = null,
        public ?int $updatedBy = null,
        public ?string $idempotencyKey = null,
    ) {}
}
