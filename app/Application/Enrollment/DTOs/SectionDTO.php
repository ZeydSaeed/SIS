<?php

namespace App\Application\Enrollment\DTOs;

final readonly class SectionDTO
{
    public function __construct(
        public int $id,
        public int $classId,
        public int $schoolId,
        public string $code,
        public string $name,
        public ?int $capacity,
        public ?int $homeroomTeacherId,
        public int $status,
        public string $createdAt,
        public string $updatedAt,
    ) {}
}
