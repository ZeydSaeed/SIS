<?php

namespace App\Application\Results\Queries;

use App\Application\Results\Contracts\TermResultSheetReadRepositoryInterface;
use App\Application\Results\Contracts\YearResultSheetReadRepositoryInterface;

final class GetYearResultSheetHandler
{
    public function __construct(
        private readonly TermResultSheetReadRepositoryInterface $lookups,
        private readonly YearResultSheetReadRepositoryInterface $sheet,
    ) {}

    /**
     * @return array{classes: list<array<string, mixed>>, rows: list<array<string, mixed>>}
     */
    public function handle(GetYearResultSheetQuery $query): array
    {
        $classOk = $query->classId !== null && $this->lookups->classInSchoolYear($query->classId, $query->schoolId, $query->academicYearId)
            && ($query->sectionId === null || $this->lookups->sectionInClass($query->sectionId, $query->classId));

        return [
            'classes' => $this->lookups->classes($query->schoolId, $query->academicYearId),
            'rows' => $classOk ? $this->sheet->rows($query->schoolId, $query->academicYearId, (int) $query->classId, $query->sectionId) : [],
        ];
    }
}
