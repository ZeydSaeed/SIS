<?php

namespace App\Application\Teachers\Queries;

final readonly class ListTeacherQualificationsQuery
{
    public function __construct(
        public int $schoolId,
        public int $teacherId,
        public int $academicYearId,
    ) {}
}
