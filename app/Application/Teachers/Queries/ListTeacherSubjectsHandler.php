<?php

namespace App\Application\Teachers\Queries;

use App\Application\Teachers\DTOs\TeacherSubjectDTO;
use App\Domain\Teachers\Repositories\TeacherRepositoryInterface;

final class ListTeacherSubjectsHandler
{
    public function __construct(
        private readonly TeacherRepositoryInterface $teachers,
    ) {}

    /**
     * @return list<TeacherSubjectDTO>|null null when teacher not in school/year
     */
    public function handle(ListTeacherSubjectsQuery $query): ?array
    {
        if (! $this->teachers->belongsToSchool($query->teacherId, $query->schoolId, $query->academicYearId)) {
            return null;
        }

        return array_map(
            fn ($row): TeacherSubjectDTO => new TeacherSubjectDTO(
                id: $row->id,
                teacherId: $row->teacherId,
                subjectId: $row->subjectId,
                academicYearId: $row->academicYearId,
                schoolId: $row->schoolId,
                createdAt: $row->createdAt,
            ),
            $this->teachers->listSubjectAssignments($query->teacherId, $query->schoolId, $query->academicYearId),
        );
    }
}
