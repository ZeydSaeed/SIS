<?php

namespace App\Domain\Exams\Data;

final readonly class CreateExamEnrollmentData
{
    public function __construct(
        public int $examSessionId,
        public int $schoolId,
        public int $enrollmentId,
        public int $status,
        public ?string $seatNumber = null,
    ) {}
}
