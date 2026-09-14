<?php

namespace App\Domain\Enrollment\Data;

final readonly class ClassSnapshot
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
