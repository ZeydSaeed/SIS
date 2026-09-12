<?php

namespace App\Domain\Results\Data;

final readonly class CurrentRankingSnapshotRead
{
    /**
     * @param  list<array{enrollment_id:int,student_id:int,gpa_result_id:int,metric_value:?string,rank_position:int}>  $entries
     */
    public function __construct(
        public int $id,
        public int $snapshotVersion,
        public string $metricCode,
        public int $participantCount,
        public array $entries,
    ) {}
}
