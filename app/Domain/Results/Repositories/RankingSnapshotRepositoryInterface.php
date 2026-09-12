<?php

namespace App\Domain\Results\Repositories;

use App\Domain\Results\Data\PersistRankingSnapshotData;
use App\Domain\Results\Data\RankingParticipant;

interface RankingSnapshotRepositoryInterface
{
    /**
     * Official year-GPA participants in school+year+class.
     *
     * @return list<RankingParticipant>
     */
    public function listOfficialYearGpaParticipants(
        int $schoolId,
        int $academicYearId,
        int $classId,
    ): array;

    public function nextSnapshotVersion(int $schoolId, int $academicYearId, int $classId): int;

    public function insertSnapshot(PersistRankingSnapshotData $data): int;

    public function findCurrentSnapshot(
        int $schoolId,
        int $academicYearId,
        int $classId,
    ): ?\App\Domain\Results\Data\CurrentRankingSnapshotRead;
}
