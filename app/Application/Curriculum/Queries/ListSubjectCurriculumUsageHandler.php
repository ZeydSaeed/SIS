<?php

namespace App\Application\Curriculum\Queries;

use App\Domain\Curriculum\Repositories\CurriculumRepositoryInterface;

final class ListSubjectCurriculumUsageHandler
{
    public function __construct(
        private readonly CurriculumRepositoryInterface $curricula,
    ) {}

    /** @return array<int, list<string>> subject id → curriculum names */
    public function handle(ListSubjectCurriculumUsageQuery $query): array
    {
        return $this->curricula->curriculumNamesBySubject($query->schoolId);
    }
}
