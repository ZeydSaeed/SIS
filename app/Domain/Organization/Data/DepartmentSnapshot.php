<?php

namespace App\Domain\Organization\Data;

final readonly class DepartmentSnapshot
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
