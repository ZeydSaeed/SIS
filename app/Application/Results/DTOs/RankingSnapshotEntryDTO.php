<?php

namespace App\Application\Results\DTOs;

final readonly class RankingSnapshotEntryDTO
{
    public function __construct(
        public int $enrollmentId,
        public int $studentId,
        public int $gpaResultId,
        public ?string $metricValue,
        public int $rankPosition,
    ) {}
}
