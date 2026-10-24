<?php

namespace App\Application\Results\Queries;

use App\Application\Results\Contracts\TermResultSheetReadRepositoryInterface;
use App\Domain\Results\Services\ResultsReconciliation;

final class GetTermResultSheetHandler
{
    public function __construct(
        private readonly TermResultSheetReadRepositoryInterface $sheet,
    ) {}

    /**
     * @return array{terms: list<array<string, mixed>>, classes: list<array<string, mixed>>, subjects: list<array<string, mixed>>, rows: list<array<string, mixed>>, reconciliation: array<string, mixed>}
     */
    public function handle(GetTermResultSheetQuery $query): array
    {
        $termOk = $query->termId !== null && $this->sheet->termInYear($query->termId, $query->academicYearId);
        $classOk = $query->classId !== null && $this->sheet->classInSchoolYear($query->classId, $query->schoolId, $query->academicYearId)
            && ($query->sectionId === null || $this->sheet->sectionInClass($query->sectionId, $query->classId));

        $rows = $termOk && $classOk && $query->subjectId !== null
            ? $this->sheet->rows($query->schoolId, $query->academicYearId, (int) $query->termId, $query->subjectId, (int) $query->classId, $query->sectionId)
            : [];

        return [
            'terms' => $this->sheet->terms($query->academicYearId),
            'classes' => $this->sheet->classes($query->schoolId, $query->academicYearId),
            'subjects' => $termOk ? $this->sheet->subjects($query->schoolId, $query->academicYearId, (int) $query->termId) : [],
            'rows' => $rows,
            'reconciliation' => ResultsReconciliation::forTerm($rows),
        ];
    }
}
