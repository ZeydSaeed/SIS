<?php

namespace App\Application\Enrollment\Queries;

use App\Application\Enrollment\DTOs\EnrollmentSubjectDTO;
use App\Domain\Enrollment\Repositories\EnrollmentRepositoryInterface;
use App\Domain\Enrollment\Repositories\EnrollmentSubjectRepositoryInterface;

final class ListEnrollmentSubjectsHandler
{
    public function __construct(
        private readonly EnrollmentRepositoryInterface $enrollments,
        private readonly EnrollmentSubjectRepositoryInterface $enrollmentSubjects,
    ) {}

    /** @return list<EnrollmentSubjectDTO>|null */
    public function handle(ListEnrollmentSubjectsQuery $query): ?array
    {
        if ($this->enrollments->findByIdAndSchool($query->enrollmentId, $query->schoolId) === null) {
            return null;
        }

        return array_map(
            fn ($row): EnrollmentSubjectDTO => new EnrollmentSubjectDTO(
                id: $row->id,
                enrollmentId: $row->enrollmentId,
                subjectId: $row->subjectId,
                isElective: $row->isElective,
                status: $row->status,
            ),
            $this->enrollmentSubjects->listActive($query->schoolId, $query->enrollmentId),
        );
    }
}
