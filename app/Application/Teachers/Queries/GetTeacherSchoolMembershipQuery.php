<?php

namespace App\Application\Teachers\Queries;

final readonly class GetTeacherSchoolMembershipQuery
{
    public function __construct(
        public int $schoolId,
        public int $teacherId,
        public int $membershipId,
        public ?int $academicYearId = null,
    ) {}
}
