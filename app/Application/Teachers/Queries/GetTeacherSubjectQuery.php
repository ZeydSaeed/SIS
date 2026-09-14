<?php

namespace App\Application\Teachers\Queries;

final readonly class GetTeacherSubjectQuery
{
    public function __construct(
        public int $schoolId,
        public int $teacherId,
        public int $assignmentId,
        public int $academicYearId,
    ) {}
}
