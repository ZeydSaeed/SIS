<?php

namespace App\Application\Enrollment\Queries;

final readonly class GetSectionQuery
{
    public function __construct(
        public int $schoolId,
        public int $sectionId,
    ) {}
}
