<?php

namespace App\Application\Results\Queries;

/** The annual / GPA sheet: classes for the filters and, once a class is chosen, its rows. */
final readonly class GetYearResultSheetQuery
{
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public ?int $classId,
        public ?int $sectionId,
    ) {}
}
