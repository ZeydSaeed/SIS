<?php

namespace App\Application\Academic\Queries;

final readonly class GetAcademicYearQuery
{
    public function __construct(
        public int $academicYearId,
    ) {}
}
