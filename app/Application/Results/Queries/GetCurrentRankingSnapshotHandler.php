<?php

namespace App\Application\Results\Queries;

use App\Application\Contracts\Query;
use App\Application\Contracts\QueryHandler;
use App\Application\Results\DTOs\CurrentRankingSnapshotDTO;
use App\Application\Results\DTOs\RankingSnapshotEntryDTO;
use App\Domain\Results\Repositories\RankingSnapshotRepositoryInterface;

final class GetCurrentRankingSnapshotHandler implements QueryHandler
{
    public const COMPARATIVE_LABEL = 'comparative_projection_not_academic_truth';

    public function __construct(
        private readonly RankingSnapshotRepositoryInterface $rankings,
    ) {}

    public function handle(Query $query): ?CurrentRankingSnapshotDTO
    {
        assert($query instanceof GetCurrentRankingSnapshotQuery);

        $row = $this->rankings->findCurrentSnapshot(
            $query->schoolId,
            $query->academicYearId,
            $query->classId,
        );

        if ($row === null) {
            return null;
        }

        $entries = [];
        foreach ($row->entries as $e) {
            $entries[] = new RankingSnapshotEntryDTO(
                enrollmentId: $e['enrollment_id'],
                studentId: $e['student_id'],
                gpaResultId: $e['gpa_result_id'],
                metricValue: $e['metric_value'],
                rankPosition: $e['rank_position'],
            );
        }

        return new CurrentRankingSnapshotDTO(
            schoolId: $query->schoolId,
            academicYearId: $query->academicYearId,
            classId: $query->classId,
            rankingSnapshotId: $row->id,
            snapshotVersion: $row->snapshotVersion,
            metricCode: $row->metricCode,
            participantCount: $row->participantCount,
            comparativeProjectionLabel: self::COMPARATIVE_LABEL,
            entries: $entries,
        );
    }
}
