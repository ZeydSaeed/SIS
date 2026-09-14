<?php

namespace App\Application\Teachers\Queries;

final readonly class GetTeacherQualificationQuery
{
    public function __construct(
        public int $schoolId,
        public int $teacherId,
        public int $qualificationId,
        public int $academicYearId,
    ) {}
}
