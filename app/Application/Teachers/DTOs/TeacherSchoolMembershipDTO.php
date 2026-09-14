<?php

namespace App\Application\Teachers\DTOs;

final readonly class TeacherSchoolMembershipDTO
{
    public function __construct(
        public int $id,
        public int $teacherId,
        public int $schoolId,
        public int $academicYearId,
        public bool $isPrimary,
        public ?string $leftAt,
        public string $createdAt,
    ) {}
}
