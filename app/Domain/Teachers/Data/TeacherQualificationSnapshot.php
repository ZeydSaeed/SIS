<?php

namespace App\Domain\Teachers\Data;

final readonly class TeacherQualificationSnapshot
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
