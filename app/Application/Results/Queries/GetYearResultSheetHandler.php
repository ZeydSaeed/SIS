<?php

namespace App\Application\Results\Queries;

use App\Application\Results\Contracts\TermResultSheetReadRepositoryInterface;
use App\Application\Results\Contracts\YearResultSheetReadRepositoryInterface;
use App\Domain\Results\Services\ResultsReconciliation;

final class GetYearResultSheetHandler
{
    public function __construct(
        private readonly TermResultSheetReadRepositoryInterface $lookups,
        private readonly YearResultSheetReadRepositoryInterface $sheet,
    ) {}

    /**
     * @return array{classes: list<array<string, mixed>>, rows: list<array<string, mixed>>, ranking: array<string, mixed>|null, reconciliation: array<string, mixed>}
     */
    public function handle(GetYearResultSheetQuery $query): array
    {
        $classOk = $query->classId !== null && $this->lookups->classInSchoolYear($query->classId, $query->schoolId, $query->academicYearId)
            && ($query->sectionId === null || $this->lookups->sectionInClass($query->sectionId, $query->classId));

        $rows = $classOk ? $this->sheet->rows($query->schoolId, $query->academicYearId, (int) $query->classId, $query->sectionId) : [];
        $ranking = $classOk ? $this->sheet->currentRanking($query->schoolId, $query->academicYearId, (int) $query->classId) : null;

        return [
            'classes' => $this->lookups->classes($query->schoolId, $query->academicYearId),
            'rows' => $rows,
            'ranking' => $ranking,
            'reconciliation' => ResultsReconciliation::forYear($rows, $ranking !== null),
        ];
    }
}
