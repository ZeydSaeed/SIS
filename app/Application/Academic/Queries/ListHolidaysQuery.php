<?php

namespace App\Application\Academic\Queries;

final readonly class ListHolidaysQuery
{
    public function __construct(
        public int $schoolId,
        public ?int $academicYearId = null,
    ) {}
}
