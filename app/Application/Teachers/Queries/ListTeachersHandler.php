<?php

namespace App\Application\Teachers\Queries;

use App\Application\Teachers\DTOs\TeacherDTO;
use App\Application\Teachers\DTOs\TeacherListPageDTO;
use App\Domain\Teachers\Data\TeacherSnapshot;
use App\Domain\Teachers\Repositories\TeacherRepositoryInterface;

final class ListTeachersHandler
{
    public function __construct(
        private readonly TeacherRepositoryInterface $teachers,
    ) {}

    public function handle(ListTeachersQuery $query): TeacherListPageDTO
    {
        $perPage = max(1, min(100, $query->perPage));
        $page = max(1, $query->page);
        $result = $this->teachers->listForSchool($query->schoolId, $query->academicYearId, $page, $perPage);

        return new TeacherListPageDTO(
            items: array_map([$this, 'map'], $result['items']),
            total: $result['total'],
            page: $page,
            perPage: $perPage,
        );
    }

    private function map(TeacherSnapshot $s): TeacherDTO
    {
        return new TeacherDTO(
            $s->id,
            $s->userId,
            $s->employeeCode,
            $s->nationalId,
            $s->firstName,
            $s->lastName,
            $s->fullName,
            $s->specializationField,
            $s->hireDate,
            $s->status,
            $s->schoolId,
            $s->academicYearId,
            $s->isPrimary,
        );
    }
}
