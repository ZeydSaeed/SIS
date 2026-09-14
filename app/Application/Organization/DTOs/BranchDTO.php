<?php

namespace App\Application\Organization\DTOs;

final readonly class BranchDTO
{
    public function __construct(
        public int $id,
        public int $schoolId,
        public string $code,
        public string $name,
        public ?string $address,
        public int $status,
        public string $createdAt,
        public string $updatedAt,
    ) {}
}
