<?php

namespace App\Domain\Organization\Data;

final readonly class BranchSnapshot
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
