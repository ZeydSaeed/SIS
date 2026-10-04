<?php

namespace App\Application\Curriculum\Queries;

use App\Application\Curriculum\DTOs\CurriculumDTO;
use App\Domain\Curriculum\Repositories\CurriculumRepositoryInterface;

final class ListCurriculaHandler
{
    public function __construct(
        private readonly CurriculumRepositoryInterface $curricula,
    ) {}

    /**
     * @return list<CurriculumDTO>|array{items: list<CurriculumDTO>, total: int, page: int, per_page: int, last_page: int}
     */
    public function handle(ListCurriculaQuery $query): array
    {
        if ($query->paginate) {
            $result = $this->curricula->searchForSchool(
                schoolId: $query->schoolId,
                academicYearId: $query->academicYearId,
                status: $query->status,
                gradeLevelId: $query->gradeLevelId,
                specializationId: $query->specializationId,
                branchId: $query->branchId,
                q: $query->q,
                page: $query->page,
                perPage: $query->perPage,
                departmentId: $query->departmentId,
            );

            $items = array_map(
                fn ($row): CurriculumDTO => $this->map($row),
                $result['items'],
            );
            $perPage = max(1, $query->perPage);
            $lastPage = max(1, (int) ceil($result['total'] / $perPage));

            return [
                'items' => $items,
                'total' => $result['total'],
                'page' => max(1, $query->page),
                'per_page' => $perPage,
                'last_page' => $lastPage,
            ];
        }

        $rows = $query->includeInactive
            ? $this->curricula->listForSchool($query->schoolId, (int) $query->academicYearId)
            : $this->curricula->listActiveForSchool($query->schoolId, (int) $query->academicYearId);

        return array_map(fn ($row): CurriculumDTO => $this->map($row), $rows);
    }

    private function map(object $row): CurriculumDTO
    {
        return new CurriculumDTO(
            id: $row->id,
            schoolId: $row->schoolId,
            academicYearId: $row->academicYearId,
            gradeLevelId: $row->gradeLevelId,
            specializationId: $row->specializationId,
            name: $row->name,
            status: $row->status,
            departmentId: $row->departmentId,
        );
    }
}
