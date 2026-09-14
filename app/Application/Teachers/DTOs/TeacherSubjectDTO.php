<?php

namespace App\Application\Teachers\DTOs;

final readonly class TeacherSubjectDTO
{
    public function __construct(
        public int $id,
        public int $teacherId,
        public int $subjectId,
        public int $academicYearId,
        public int $schoolId,
        public string $createdAt,
    ) {}
}
