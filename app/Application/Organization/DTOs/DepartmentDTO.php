<?php

namespace App\Application\Organization\DTOs;

final readonly class DepartmentDTO
{
    public function __construct(
        public int $id,
        public int $schoolId,
        public ?int $branchId,
        public string $code,
        public string $name,
        public int $departmentType,
        public int $status,
        public string $createdAt,
        public string $updatedAt,
    ) {}
}
