<?php

namespace App\Application\Enrollment\Queries;

use App\Application\Enrollment\DTOs\EnrollmentSubjectDTO;
use App\Domain\Enrollment\Repositories\EnrollmentSubjectRepositoryInterface;

final class GetEnrollmentSubjectHandler
{
    public function __construct(
        private readonly EnrollmentSubjectRepositoryInterface $enrollmentSubjects,
    ) {}

    public function handle(GetEnrollmentSubjectQuery $query): ?EnrollmentSubjectDTO
    {
        $row = $this->enrollmentSubjects->findActive($query->schoolId, $query->linkId)
            ?? $this->enrollmentSubjects->findInactive($query->schoolId, $query->linkId);
        if ($row === null) {
            return null;
        }

        return new EnrollmentSubjectDTO(
            id: $row->id,
            enrollmentId: $row->enrollmentId,
            subjectId: $row->subjectId,
            isElective: $row->isElective,
            status: $row->status,
        );
    }
}
