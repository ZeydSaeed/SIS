<?php

namespace App\Domain\Student\Data;

final readonly class StudentGuardianLinkSnapshot
{
    public function __construct(
        public int $linkId,
        public int $studentId,
        public int $guardianId,
        public int $relationshipType,
        public bool $isPrimary,
        public bool $isEmergencyContact,
        public string $guardianFullName,
        public ?string $guardianPhone,
        public ?string $guardianEmail,
        public string $createdAt,
    ) {}
}
