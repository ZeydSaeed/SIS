<?php

namespace App\Domain\Organization\Data;

final readonly class RoomSnapshot
{
    public function __construct(
        public int $id,
        public int $branchId,
        public int $schoolId,
        public string $code,
        public string $name,
        public ?int $capacity,
        public int $roomType,
        public int $status,
        public string $createdAt,
        public string $updatedAt,
    ) {}
}
