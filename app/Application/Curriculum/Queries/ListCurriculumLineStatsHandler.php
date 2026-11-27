<?php

namespace App\Application\Curriculum\Queries;

use App\Domain\Curriculum\Repositories\CurriculumRepositoryInterface;

final class ListCurriculumLineStatsHandler
{
    public function __construct(
        private readonly CurriculumRepositoryInterface $curricula,
    ) {}

    /**
     * A curriculum is complete («مكتمل») when it has subjects and every active subject has its main teacher.
     *
     * @return array<int, array{subject_count: int, total_hours: int, staffed_count: int, is_complete: bool}>
     */
    public function handle(ListCurriculumLineStatsQuery $query): array
    {
        $stats = $this->curricula->lineStats($query->schoolId, $query->curriculumIds);
        $result = [];
        foreach ($query->curriculumIds as $id) {
            $row = $stats[$id] ?? ['subjects' => 0, 'hours' => 0, 'staffed' => 0];
            $result[$id] = [
                'subject_count' => $row['subjects'],
                'total_hours' => $row['hours'],
                'staffed_count' => $row['staffed'],
                'is_complete' => $row['subjects'] > 0 && $row['staffed'] === $row['subjects'],
            ];
        }

        return $result;
    }
}
