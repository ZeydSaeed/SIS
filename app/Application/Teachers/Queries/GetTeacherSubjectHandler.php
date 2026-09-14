<?php

namespace App\Application\Teachers\Queries;

use App\Application\Teachers\DTOs\TeacherSubjectDTO;
use App\Domain\Teachers\Repositories\TeacherRepositoryInterface;

final class GetTeacherSubjectHandler
{
    public function __construct(
        private readonly TeacherRepositoryInterface $teachers,
    ) {}

    public function handle(GetTeacherSubjectQuery $query): ?TeacherSubjectDTO
    {
        if (! $this->teachers->belongsToSchool($query->teacherId, $query->schoolId, $query->academicYearId)) {
            return null;
        }

        $row = $this->teachers->findSubjectAssignmentById(
            $query->teacherId,
            $query->schoolId,
            $query->assignmentId,
        );

        if ($row === null || $row->academicYearId !== $query->academicYearId) {
            return null;
        }

        return new TeacherSubjectDTO(
            id: $row->id,
            teacherId: $row->teacherId,
            subjectId: $row->subjectId,
            academicYearId: $row->academicYearId,
            schoolId: $row->schoolId,
            createdAt: $row->createdAt,
        );
    }
}
