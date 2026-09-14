<?php

namespace App\Application\Student\DTOs;

final readonly class StudentGuardianLinkDTO
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
