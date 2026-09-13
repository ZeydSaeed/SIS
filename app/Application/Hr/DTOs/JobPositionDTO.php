<?php

namespace App\Application\Hr\DTOs;

final readonly class JobPositionDTO
{
    public function __construct(
        public int $id,
        public int $schoolId,
        public string $code,
        public string $name,
        public int $category,
        public int $status,
        public string $createdAt,
        public string $updatedAt,
    ) {}
}
