<?php

namespace App\Application\Enrollment\Queries;

final readonly class ListClassSectionsQuery
{
    public function __construct(
        public int $schoolId,
        public int $classId,
    ) {}
}
