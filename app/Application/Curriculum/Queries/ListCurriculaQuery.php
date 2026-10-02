<?php

namespace App\Application\Curriculum\Queries;

final readonly class ListCurriculaQuery
{
    public function __construct(
        public int $schoolId,
        public ?int $academicYearId,
        public bool $includeInactive = false,
        public ?int $status = null,
        public ?int $gradeLevelId = null,
        public ?int $specializationId = null,
        public ?int $branchId = null,
        public string $q = '',
        public int $page = 1,
        public int $perPage = 25,
        public bool $paginate = false,
    ) {}
}
