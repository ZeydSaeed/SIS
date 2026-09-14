<?php

namespace App\Application\Curriculum\Queries;

use App\Application\Curriculum\DTOs\CurriculumSubjectDTO;
use App\Domain\Curriculum\Repositories\CurriculumRepositoryInterface;

final class GetCurriculumSubjectHandler
{
    public function __construct(
        private readonly CurriculumRepositoryInterface $curricula,
    ) {}

    public function handle(GetCurriculumSubjectQuery $query): ?CurriculumSubjectDTO
    {
        $row = $this->curricula->findActiveLink($query->schoolId, $query->linkId)
            ?? $this->curricula->findInactiveLink($query->schoolId, $query->linkId);
        if ($row === null) {
            return null;
        }

        return new CurriculumSubjectDTO(
            id: $row->id,
            curriculumId: $row->curriculumId,
            subjectId: $row->subjectId,
            weeklyHours: $row->weeklyHours,
            isRequired: $row->isRequired,
            subjectOrder: $row->subjectOrder,
            status: $row->status,
        );
    }
}
