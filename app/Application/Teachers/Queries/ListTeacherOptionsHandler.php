<?php

namespace App\Application\Teachers\Queries;

use App\Domain\Teachers\Repositories\TeacherRepositoryInterface;

final class ListTeacherOptionsHandler
{
    public function __construct(
        private readonly TeacherRepositoryInterface $teachers,
    ) {}

    /** @return list<array{id:int, name:string, employee_code:string}> */
    public function handle(ListTeacherOptionsQuery $query): array
    {
        return $this->teachers->listTeacherOptions($query->schoolId, $query->academicYearId);
    }
}
