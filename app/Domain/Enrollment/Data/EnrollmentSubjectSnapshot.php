<?php

namespace App\Domain\Enrollment\Data;

final readonly class EnrollmentSubjectSnapshot
{
    public function __construct(
        public int $id,
        public int $enrollmentId,
        public int $subjectId,
        public bool $isElective,
        public int $status,
    ) {}
}
