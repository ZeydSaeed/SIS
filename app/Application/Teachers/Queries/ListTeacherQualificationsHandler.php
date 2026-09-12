<?php

namespace App\Application\Teachers\Queries;

use App\Application\Teachers\DTOs\TeacherQualificationDTO;
use App\Domain\Teachers\Repositories\TeacherRepositoryInterface;

final class ListTeacherQualificationsHandler
{
    public function __construct(
        private readonly TeacherRepositoryInterface $teachers,
    ) {}

    /**
     * @return list<TeacherQualificationDTO>|null null when teacher not in school/year
     */
    public function handle(ListTeacherQualificationsQuery $query): ?array
    {
        if (! $this->teachers->belongsToSchool($query->teacherId, $query->schoolId, $query->academicYearId)) {
            return null;
        }

        $items = [];
        foreach ($this->teachers->listQualifications($query->teacherId) as $row) {
            $items[] = new TeacherQualificationDTO(
                $row->id,
                $row->teacherId,
                $row->qualificationType,
                $row->title,
                $row->institution,
                $row->yearObtained,
                $row->documentStorageKey,
                $row->status,
                $row->effectiveFrom,
                $row->effectiveTo,
                $row->createdAt,
            );
        }

        return $items;
    }
}
