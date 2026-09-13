<?php

namespace App\Domain\Hr\Data;

final readonly class JobPositionSnapshot
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
