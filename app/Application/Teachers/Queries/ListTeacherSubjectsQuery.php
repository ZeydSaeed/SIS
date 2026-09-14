<?php

namespace App\Application\Teachers\Queries;

final readonly class ListTeacherSubjectsQuery
{
    public function __construct(
        public int $schoolId,
        public int $teacherId,
        public int $academicYearId,
    ) {}
}
