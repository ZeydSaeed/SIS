<?php

namespace App\Application\Enrollment\Queries;

use App\Application\Contracts\Query;

final readonly class ListEnrollmentsQuery implements Query
{
    /**
     * @param  list<int>|int|null  $classId  one id or several (the ribbon filters accept more than one choice)
     * @param  list<int>|int|null  $sectionId
     * @param  list<int>|int|null  $branchId
     * @param  list<int>|int|null  $departmentId
     */
    public function __construct(
        public int $schoolId,
        public ?int $academicYearId,
        public int $page = 1,
        public int $perPage = 25,
        public ?int $status = null,
        public string $q = '',
        public ?int $gender = null,
        public int|array|null $classId = null,
        public int|array|null $sectionId = null,
        public ?string $departmentName = null,
        public ?int $specializationId = null,
        public int|array|null $branchId = null,
        public int|array|null $departmentId = null,
    ) {}
}
