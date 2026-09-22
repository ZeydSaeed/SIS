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
        public ?int $classId = null,
        public ?int $sectionId = null,
        public ?int $branchId = null,
        public ?int $departmentId = null,
        public ?int $specializationId = null,
        public ?int $gender = null,
        public bool $updateClass = false,
        public bool $updateSection = false,
        public bool $updateBranch = false,
        public bool $updateDepartment = false,
        public bool $updateSpecialization = false,
        public bool $updateGender = false,
        public bool $allowInactive = false,
        public ?int $updatedBy = null,
        public ?string $idempotencyKey = null,
    ) {}
}
