<?php

namespace App\Application\Curriculum\Queries;

use App\Application\Curriculum\DTOs\SubjectDTO;
use App\Domain\Curriculum\Repositories\SubjectRepositoryInterface;

final class ListSubjectsHandler
{
    public function __construct(
        private readonly SubjectRepositoryInterface $subjects,
    ) {}

    /**
     * @return list<SubjectDTO>|array{items: list<SubjectDTO>, total: int, page: int, per_page: int, last_page: int}
     */
    public function handle(ListSubjectsQuery $query): array
    {
        if ($query->paginate) {
            $result = $this->subjects->search(
                status: $query->status,
                subjectType: $query->subjectType,
                q: $query->q,
                page: $query->page,
                perPage: $query->perPage,
            );

            $items = array_map(fn ($row): SubjectDTO => $this->map($row), $result['items']);
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
            ? $this->subjects->listAll()
            : $this->subjects->listActive();

        return array_map(fn ($row): SubjectDTO => $this->map($row), $rows);
    }

    private function map(object $row): SubjectDTO
    {
        return new SubjectDTO(
            id: $row->id,
            code: $row->code,
            name: $row->name,
            nameEn: $row->nameEn,
            subjectType: $row->subjectType,
            creditHours: $row->creditHours,
            maxGrade: $row->maxGrade,
            passGrade: $row->passGrade,
            status: $row->status,
            prerequisitesText: $row->prerequisitesText,
            abbreviation: $row->abbreviation,
            colorHue: $row->colorHue,
        );
    }
}
