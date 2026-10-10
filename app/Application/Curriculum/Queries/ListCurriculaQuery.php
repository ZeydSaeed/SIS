<?php

namespace App\Application\Curriculum\Queries;

final readonly class ListCurriculaQuery
{
    public function __construct(
        public int $schoolId,
        public ?int $academicYearId,
        public bool $includeInactive = false,
        public ?int $status = null,
        /** One id or a list of ids (the page's ribbon filters take several choices). */
        public int|array|null $gradeLevelId = null,
        public int|array|null $specializationId = null,
        public int|array|null $branchId = null,
        public string $q = '',
        public int $page = 1,
        public int $perPage = 25,
        public bool $paginate = false,
        public int|array|null $departmentId = null,
    ) {}
}
