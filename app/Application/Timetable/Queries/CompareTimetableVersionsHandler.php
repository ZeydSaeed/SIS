<?php

namespace App\Application\Timetable\Queries;

use App\Application\Timetable\DTOs\TimetableInsightDTO;
use App\Domain\Timetable\Repositories\TimetableVersionRepositoryInterface;
use App\Domain\Timetable\Repositories\TimetableWorkspaceReadRepositoryInterface;
use App\Domain\Timetable\Services\TimetableVersionComparer;

final class CompareTimetableVersionsHandler
{
    public function __construct(
        private readonly TimetableVersionRepositoryInterface $versions,
        private readonly TimetableWorkspaceReadRepositoryInterface $workspace,
        private readonly TimetableVersionComparer $comparer,
    ) {}

    public function handle(CompareTimetableVersionsQuery $query): ?TimetableInsightDTO
    {
        $side = function (?int $versionId) use ($query): ?array {
            if ($versionId === null) {
                return ['lessons' => $this->workspace->activeSchedules($query->schoolId, $query->academicYearId), 'quality' => null, 'label' => null];
            }
            $version = $this->versions->find($query->schoolId, $versionId);
            if ($version === null || $version['academic_year_id'] !== $query->academicYearId) {
                return null;
            }

            return ['lessons' => $this->versions->entries($query->schoolId, $versionId), 'quality' => $version['quality'], 'label' => $version['version_no']];
        };
        $a = $side($query->versionA);
        $b = $side($query->versionB);
        if ($a === null || $b === null) {
            return null;
        }

        return new TimetableInsightDTO($this->comparer->compare($a['lessons'], $b['lessons']) + [
            'quality' => ['a' => $a['quality']['overall'] ?? null, 'b' => $b['quality']['overall'] ?? null],
            'labels' => ['a' => $a['label'], 'b' => $b['label']],
        ]);
    }
}
