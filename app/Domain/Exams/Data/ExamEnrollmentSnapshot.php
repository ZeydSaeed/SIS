<?php

namespace App\Domain\Exams\Data;

final readonly class ExamEnrollmentSnapshot
{
    public function __construct(
        public int $id,
        public int $examSessionId,
        public int $schoolId,
        public int $enrollmentId,
        public int $status,
        public ?string $seatNumber,
    ) {}
}
