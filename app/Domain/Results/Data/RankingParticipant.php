<?php

namespace App\Domain\Results\Data;

final readonly class RankingParticipant
{
    public function __construct(
        public int $enrollmentId,
        public int $studentId,
        public int $gpaResultId,
        public ?string $metricValue,
    ) {}
}
