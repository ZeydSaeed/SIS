<?php

namespace App\Domain\Teachers\Data;

final readonly class TeacherSubjectSnapshot
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
