<?php

namespace App\Application\Enrollment\Commands;

use App\Application\Contracts\Command;

/**
 * Enroll several students into one placement in a single server call.
 * Each student is enrolled independently; ineligible ones are skipped with a reason.
 */
final readonly class BulkEnrollStudentsCommand implements Command
{
    /**
     * @param  list<int>  $studentIds
     */
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public array $studentIds,
        public int $classId,
        public int $sectionId,
        public string $effectiveFrom,
        public ?int $branchId = null,
        public ?int $departmentId = null,
        public ?int $enrolledBy = null,
        public ?string $idempotencyKey = null,
    ) {}
}
