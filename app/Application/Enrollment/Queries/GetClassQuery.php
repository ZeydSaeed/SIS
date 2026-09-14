<?php

namespace App\Application\Enrollment\Queries;

final readonly class GetClassQuery
{
    public function __construct(
        public int $schoolId,
        public int $classId,
    ) {}
}
