<?php

namespace App\Application\Teachers\DTOs;

final readonly class TeacherQualificationDTO
{
    public function __construct(
        public int $id,
        public int $teacherId,
        public int $qualificationType,
        public string $title,
        public ?string $institution,
        public ?int $yearObtained,
        public ?string $documentStorageKey,
        public int $status,
        public string $effectiveFrom,
        public ?string $effectiveTo,
        public string $createdAt,
    ) {}
}
