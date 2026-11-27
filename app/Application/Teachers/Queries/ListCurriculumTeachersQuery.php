<?php

namespace App\Application\Teachers\Queries;

/** The teachers of a curriculum's subject lines, and the school's teachers to choose from. */
final readonly class ListCurriculumTeachersQuery
{
    public function __construct(
        public int $schoolId,
        public int $curriculumId,
        public int $academicYearId,
    ) {}
}
