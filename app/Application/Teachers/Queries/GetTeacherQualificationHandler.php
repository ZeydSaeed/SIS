<?php

namespace App\Application\Teachers\Queries;

use App\Application\Teachers\DTOs\TeacherQualificationDTO;
use App\Domain\Teachers\Repositories\TeacherRepositoryInterface;

final class GetTeacherQualificationHandler
{
    public function __construct(
        private readonly TeacherRepositoryInterface $teachers,
    ) {}

    public function handle(GetTeacherQualificationQuery $query): ?TeacherQualificationDTO
    {
        if (! $this->teachers->belongsToSchool($query->teacherId, $query->schoolId, $query->academicYearId)) {
            return null;
        }

        $row = $this->teachers->findQualification($query->teacherId, $query->qualificationId);
        if ($row === null) {
            return null;
        }

        return new TeacherQualificationDTO(
            id: $row->id,
            teacherId: $row->teacherId,
            qualificationType: $row->qualificationType,
            title: $row->title,
            institution: $row->institution,
            yearObtained: $row->yearObtained,
            documentStorageKey: $row->documentStorageKey,
            status: $row->status,
            effectiveFrom: $row->effectiveFrom,
            effectiveTo: $row->effectiveTo,
            createdAt: $row->createdAt,
        );
    }
}
