<?php

namespace App\Domain\Exams\Data;

final readonly class CreateExamSessionData
{
    public function __construct(
        public int $examId,
        public int $schoolId,
        public int $subjectId,
        public string $sessionDate,
        public string $startTime,
        public string $endTime,
        public ?int $roomId,
        public int $maxGrade,
        public int $passGrade,
        public int $status,
    ) {}
}
