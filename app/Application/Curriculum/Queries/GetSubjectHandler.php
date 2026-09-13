<?php

namespace App\Application\Curriculum\Queries;

use App\Application\Curriculum\DTOs\SubjectDTO;
use App\Domain\Curriculum\Repositories\SubjectRepositoryInterface;

final class GetSubjectHandler
{
    public function __construct(
        private readonly SubjectRepositoryInterface $subjects,
    ) {}

    public function handle(GetSubjectQuery $query): ?SubjectDTO
    {
        $s = $this->subjects->findActive($query->subjectId)
            ?? $this->subjects->findInactive($query->subjectId);
        if ($s === null) {
            return null;
        }

        return new SubjectDTO(
            id: $s->id,
            code: $s->code,
            name: $s->name,
            nameEn: $s->nameEn,
            subjectType: $s->subjectType,
            creditHours: $s->creditHours,
            maxGrade: $s->maxGrade,
            passGrade: $s->passGrade,
            status: $s->status,
        );
    }
}
