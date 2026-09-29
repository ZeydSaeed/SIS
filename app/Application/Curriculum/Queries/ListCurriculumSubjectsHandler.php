<?php

namespace App\Application\Curriculum\Queries;

use App\Application\Curriculum\DTOs\CurriculumSubjectDTO;
use App\Domain\Curriculum\Repositories\CurriculumRepositoryInterface;

final class ListCurriculumSubjectsHandler
{
    public function __construct(
        private readonly CurriculumRepositoryInterface $curricula,
    ) {}

    /** @return list<CurriculumSubjectDTO>|null null when curriculum missing */
    public function handle(ListCurriculumSubjectsQuery $query): ?array
    {
        $curriculum = $this->curricula->findActiveInSchool($query->schoolId, $query->curriculumId)
            ?? ($query->includeInactive
                ? $this->curricula->findInactiveInSchool($query->schoolId, $query->curriculumId)
                : null);

        if ($curriculum === null) {
            return null;
        }

        $rows = $query->includeInactive
            ? $this->curricula->listLinks($query->schoolId, $query->curriculumId)
            : $this->curricula->listActiveLinks($query->schoolId, $query->curriculumId);

        return array_map(
            fn ($row): CurriculumSubjectDTO => new CurriculumSubjectDTO(
                id: $row->id,
                curriculumId: $row->curriculumId,
                subjectId: $row->subjectId,
                weeklyHours: $row->weeklyHours,
                isRequired: $row->isRequired,
                subjectOrder: $row->subjectOrder,
                status: $row->status,
            ),
            $rows,
        );
    }
}
