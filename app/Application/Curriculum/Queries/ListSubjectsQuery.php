<?php

namespace App\Application\Curriculum\Queries;

final readonly class ListSubjectsQuery
{
    public function __construct(
        public bool $includeInactive = false,
        public ?int $status = null,
        public ?int $subjectType = null,
        public string $q = '',
        public int $page = 1,
        public int $perPage = 25,
        public bool $paginate = false,
    ) {}
}
