<?php

namespace App\Application\Student\Queries;

use App\Application\Student\DTOs\StudentGuardianLinkDTO;
use App\Domain\Student\Repositories\StudentGuardianRepositoryInterface;

final class ListStudentGuardiansHandler
{
    public function __construct(
        private readonly StudentGuardianRepositoryInterface $guardians,
    ) {}

    /**
     * @return list<StudentGuardianLinkDTO>|null
     */
    public function handle(ListStudentGuardiansQuery $query): ?array
    {
        $rows = $this->guardians->listForStudent($query->schoolId, $query->studentId);
        if ($rows === null) {
            return null;
        }

        return array_map(
            static fn ($row): StudentGuardianLinkDTO => new StudentGuardianLinkDTO(
                linkId: $row->linkId,
                studentId: $row->studentId,
                guardianId: $row->guardianId,
                relationshipType: $row->relationshipType,
                isPrimary: $row->isPrimary,
                isEmergencyContact: $row->isEmergencyContact,
                guardianFullName: $row->guardianFullName,
                guardianPhone: $row->guardianPhone,
                guardianEmail: $row->guardianEmail,
                createdAt: $row->createdAt,
            ),
            $rows,
        );
    }
}
