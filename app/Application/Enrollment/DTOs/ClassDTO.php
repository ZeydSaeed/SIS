<?php

namespace App\Application\Enrollment\DTOs;

final readonly class ClassDTO
{
    public function __construct(
        public int $id,
        public int $schoolId,
        public int $academicYearId,
        public int $gradeLevelId,
        public string $code,
        public string $name,
        public ?int $capacity,
        public int $status,
        public string $createdAt,
        public string $updatedAt,
    ) {}
}
