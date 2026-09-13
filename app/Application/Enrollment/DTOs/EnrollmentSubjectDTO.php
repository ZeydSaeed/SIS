<?php

namespace App\Application\Enrollment\DTOs;

final readonly class EnrollmentSubjectDTO
{
    public function __construct(
        public int $id,
        public int $enrollmentId,
        public int $subjectId,
        public bool $isElective,
        public int $status,
    ) {}
}
