<?php

namespace App\Application\Teachers\Queries;

/** The school's active teachers for a year — the «المدرس» pickers on «المناهج». */
final readonly class ListTeacherOptionsQuery
{
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
    ) {}
}
