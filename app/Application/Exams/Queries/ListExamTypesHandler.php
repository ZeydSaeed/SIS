<?php

namespace App\Application\Exams\Queries;

use App\Domain\Exams\Data\ExamTypeSnapshot;
use App\Domain\Exams\Repositories\ExamTypeRepositoryInterface;
use App\Domain\Exams\Repositories\ExamTypeWeightVersionRepositoryInterface;

final class ListExamTypesHandler
{
    public function __construct(
        private readonly ExamTypeRepositoryInterface $types,
        private readonly ExamTypeWeightVersionRepositoryInterface $versions,
    ) {}

    /**
     * `year_weight` / `year_version` = the effective weight in the chosen academic year (the newest version, else the
     * base weight with version null); `history` = that year's versions of the type.
     *
     * @return list<array{id: int, code: string, name: string, weight_percentage: int, in_use: bool, year_weight: int, year_version: ?int, history: list<array<string, mixed>>}>
     */
    public function handle(ListExamTypesQuery $query): array
    {
        $newest = $query->academicYearId === null ? [] : $this->versions->newestForYear($query->schoolId, $query->academicYearId);

        return array_map(fn (ExamTypeSnapshot $type): array => [
            'id' => $type->id,
            'code' => $type->code,
            'name' => $type->name,
            'weight_percentage' => $type->weightPercentage,
            'in_use' => $type->inUse,
            'year_weight' => $newest[$type->id]['weight_percentage'] ?? $type->weightPercentage,
            'year_version' => $newest[$type->id]['version'] ?? null,
            'history' => $query->academicYearId === null || ! isset($newest[$type->id])
                ? []
                : $this->versions->history($query->schoolId, $type->id, $query->academicYearId),
        ], $this->types->listForSchool($query->schoolId));
    }
}
