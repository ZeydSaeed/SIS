<?php

namespace App\Application\Results\Queries;

/** The term-results sheet: choices for the filters and, once all are chosen, its rows. */
final readonly class GetTermResultSheetQuery
{
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public ?int $termId,
        public ?int $subjectId,
        public ?int $classId,
        public ?int $sectionId,
    ) {}
}
