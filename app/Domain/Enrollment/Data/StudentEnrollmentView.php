<?php

namespace App\Domain\Enrollment\Data;

final readonly class StudentEnrollmentView
{
    public function __construct(
        public int $id,
        public int $status,
        public string $studentCode,
        public string $fullName,
    ) {}
}
