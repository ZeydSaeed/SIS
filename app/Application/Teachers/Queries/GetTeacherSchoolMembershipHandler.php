<?php

namespace App\Application\Teachers\Queries;

use App\Application\Teachers\DTOs\TeacherSchoolMembershipDTO;
use App\Domain\Teachers\Repositories\TeacherRepositoryInterface;

final class GetTeacherSchoolMembershipHandler
{
    public function __construct(
        private readonly TeacherRepositoryInterface $teachers,
    ) {}

    public function handle(GetTeacherSchoolMembershipQuery $query): ?TeacherSchoolMembershipDTO
    {
        $row = $this->teachers->findSchoolMembershipById(
            $query->teacherId,
            $query->schoolId,
            $query->membershipId,
        );

        if ($row === null) {
            return null;
        }

        if ($query->academicYearId !== null && $row->academicYearId !== $query->academicYearId) {
            return null;
        }

        return new TeacherSchoolMembershipDTO(
            id: $row->id,
            teacherId: $row->teacherId,
            schoolId: $row->schoolId,
            academicYearId: $row->academicYearId,
            isPrimary: $row->isPrimary,
            leftAt: $row->leftAt,
            createdAt: $row->createdAt,
        );
    }
}
