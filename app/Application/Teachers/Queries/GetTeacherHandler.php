<?php

namespace App\Application\Teachers\Queries;

use App\Application\Teachers\DTOs\TeacherDTO;
use App\Domain\Teachers\Repositories\TeacherRepositoryInterface;

final class GetTeacherHandler
{
    public function __construct(
        private readonly TeacherRepositoryInterface $teachers,
    ) {}

    public function handle(GetTeacherQuery $query): ?TeacherDTO
    {
        $s = $this->teachers->findInSchool($query->teacherId, $query->schoolId, $query->academicYearId);
        if ($s === null) {
            return null;
        }

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
