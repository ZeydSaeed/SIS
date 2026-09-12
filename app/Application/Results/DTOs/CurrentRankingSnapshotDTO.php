<?php

namespace App\Application\Results\DTOs;

final readonly class CurrentRankingSnapshotDTO
{
    /**
     * @param  list<RankingSnapshotEntryDTO>  $entries
     */
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public int $classId,
        public int $rankingSnapshotId,
        public int $snapshotVersion,
        public string $metricCode,
        public int $participantCount,
        public string $comparativeProjectionLabel,
        public array $entries,
    ) {}
}
