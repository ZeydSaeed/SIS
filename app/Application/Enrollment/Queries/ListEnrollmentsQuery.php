<?php

namespace App\Application\Enrollment\Queries;

use App\Application\Contracts\Query;

final readonly class ListEnrollmentsQuery implements Query
{
    public function __construct(
        public int $schoolId,
        public ?int $academicYearId,
        public int $page = 1,
        public int $perPage = 25,
        public ?int $status = null,
        public string $q = '',
        public ?int $gender = null,
        public ?int $classId = null,
        public ?int $sectionId = null,
        public ?string $departmentName = null,
        public ?int $specializationId = null,
        public ?int $branchId = null,
        public ?int $departmentId = null,
    ) {}
}
