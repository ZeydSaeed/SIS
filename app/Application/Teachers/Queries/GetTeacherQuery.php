<?php

namespace App\Application\Teachers\Queries;

final readonly class GetTeacherQuery
{
    public function __construct(
        public int $schoolId,
        public int $teacherId,
        public ?int $academicYearId = null,
    ) {}
}
