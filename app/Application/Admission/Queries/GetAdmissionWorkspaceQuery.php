<?php

namespace App\Application\Admission\Queries;

use App\Application\Contracts\Query;

final readonly class GetAdmissionWorkspaceQuery implements Query
{
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public ?int $applicationPeriodId = null,
        public ?int $statusFilter = null,
        public bool $includeApplications = true,
        public int $page = 1,
        public int $perPage = 17,
        public ?string $search = null,
        public ?string $enrollmentStatus = null,
    ) {}
}
