<?php

namespace App\Domain\Results\Data;

final readonly class RankedParticipant
{
    public function __construct(
        public int $enrollmentId,
        public int $studentId,
        public int $gpaResultId,
        public ?string $metricValue,
        public int $rankPosition,
    ) {}
}
