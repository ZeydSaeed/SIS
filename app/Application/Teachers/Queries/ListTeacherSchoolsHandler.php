<?php

namespace App\Application\Teachers\Queries;

use App\Application\Teachers\DTOs\TeacherSchoolMembershipDTO;
use App\Domain\Teachers\Repositories\TeacherRepositoryInterface;

final class ListTeacherSchoolsHandler
{
    public function __construct(
        private readonly TeacherRepositoryInterface $teachers,
    ) {}

    /**
     * @return list<TeacherSchoolMembershipDTO>|null
     */
    public function handle(ListTeacherSchoolsQuery $query): ?array
    {
        $rows = $this->teachers->listSchoolMemberships(
            $query->teacherId,
            $query->schoolId,
            $query->academicYearId,
        );

        if ($rows === null) {
            return null;
        }

        return array_map(
            static fn ($row): TeacherSchoolMembershipDTO => new TeacherSchoolMembershipDTO(
                id: $row->id,
                teacherId: $row->teacherId,
                schoolId: $row->schoolId,
                academicYearId: $row->academicYearId,
                isPrimary: $row->isPrimary,
                leftAt: $row->leftAt,
                createdAt: $row->createdAt,
            ),
            $rows,
        );
    }
}
