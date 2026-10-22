<?php

namespace App\Application\Exams\DTOs;

/** One row of a grade-entry sheet submission. */
final readonly class SessionGradeRowDTO
{
    public function __construct(
        public int $examEnrollmentId,
        public ?string $score,
        public bool $isAbsent,
    ) {}
}
